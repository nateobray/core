#!/usr/bin/env python3
"""Run integration tests on a private, temporary MySQL server, never an existing service."""
import os
from pathlib import Path
import secrets
import shutil
import signal
import socket
import subprocess
import sys
import tempfile
import time


def main():
    mysqld = shutil.which(os.environ.get("OBRAY_TEST_MYSQLD", "mysqld"))
    php = shutil.which(os.environ.get("OBRAY_TEST_PHP", "php"))
    if not mysqld or not php:
        raise RuntimeError("Install MySQL server and PHP with pdo_mysql, or set OBRAY_TEST_MYSQLD/OBRAY_TEST_PHP.")
    version = subprocess.check_output([mysqld, "--no-defaults", "--version"], text=True).strip()
    print(version, flush=True)
    password = secrets.token_hex(32)
    server_id = secrets.randbelow(2**31 - 1) + 1
    with tempfile.TemporaryDirectory(prefix="obray-mysql-") as directory:
        root = Path(directory).resolve()
        data = root / "data"
        data.mkdir(mode=0o700)
        files = root / "files"
        files.mkdir(mode=0o700)
        log = root / "mysql.log"
        init_file = root / "init.sql"
        init_file.write_text(f"ALTER USER 'root'@'localhost' IDENTIFIED BY '{password}';\n")
        init_file.chmod(0o600)
        common = [mysqld, "--no-defaults", f"--datadir={data}", f"--log-error={log}",
                  "--mysqlx=OFF", "--skip-log-bin", "--innodb-buffer-pool-size=64M",
                  "--innodb-redo-log-capacity=32M"]
        server = None
        test = None
        try:
            print("Initializing disposable MySQL storage...", flush=True)
            subprocess.run(common + ["--initialize-insecure"], check=True, timeout=60,
                           stdout=subprocess.DEVNULL, stderr=subprocess.STDOUT)
            with socket.socket() as listener:
                listener.bind(("127.0.0.1", 0))
                port = listener.getsockname()[1]
            server = subprocess.Popen(common + [f"--port={port}", "--bind-address=127.0.0.1",
                f"--socket={root / 'mysql.sock'}", f"--pid-file={root / 'mysql.pid'}",
                f"--tmpdir={root}", f"--secure-file-priv={files}", f"--init-file={init_file}",
                f"--server-id={server_id}", "--performance-schema=OFF", "--max-connections=12"],
                stdout=subprocess.DEVNULL, stderr=subprocess.STDOUT)
            deadline = time.monotonic() + 45
            while True:
                if server.poll() is not None:
                    raise RuntimeError("Disposable MySQL server exited before becoming ready.")
                try:
                    with socket.create_connection(("127.0.0.1", port), timeout=0.2):
                        break
                except OSError:
                    if time.monotonic() >= deadline:
                        raise RuntimeError("Timed out waiting for disposable MySQL.")
                    time.sleep(0.1)
            init_file.unlink()
            env = os.environ.copy()
            env.update(OBRAY_MYSQL_TEST_PORT=str(port), OBRAY_MYSQL_TEST_PASSWORD=password,
                       OBRAY_MYSQL_TEST_SERVER_ID=str(server_id), OBRAY_MYSQL_TEST_DATADIR=str(data),
                       OBRAY_MYSQL_TEST_DATABASE="obray_core_test_" + secrets.token_hex(8))
            print("Running integration tests against the new local instance...", flush=True)
            test = subprocess.Popen([php, str(Path(__file__).with_name("IntegrationTest.php"))],
                                    env=env, stdin=subprocess.DEVNULL)
            status = test.wait(timeout=90)
            if status:
                raise RuntimeError(f"MySQL integration tests failed (exit {status}).")
        except BaseException:
            if (server is None or server.poll() is not None) and log.exists():
                tail = "\n".join(log.read_text(errors="replace").splitlines()[-15:])
                print(tail.replace(password, "[redacted]"), file=sys.stderr)
            raise
        finally:
            for process in (test, server):
                if process is not None and process.poll() is None:
                    process.terminate()
                    try:
                        process.wait(timeout=15)
                    except subprocess.TimeoutExpired:
                        process.kill()
                        process.wait(timeout=5)
    print("Temporary MySQL server stopped; test storage removed.", flush=True)


if __name__ == "__main__":
    signal.signal(signal.SIGTERM, lambda *_: sys.exit(143))
    try:
        main()
    except (RuntimeError, subprocess.SubprocessError, OSError) as error:
        print(str(error), file=sys.stderr)
        sys.exit(1)
