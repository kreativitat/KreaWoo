"""Security regressions for the public source history boundary."""

# Copyright (C) 2026 Kreativität Works <mail@kreativitat.com>
# SPDX-License-Identifier: GPL-3.0-or-later

import importlib.util
import os
import subprocess
import tempfile
import unittest
from pathlib import Path

SPEC = importlib.util.spec_from_file_location(
    "publication_guard", Path(__file__).resolve().parents[1] / "scripts/check_publication.py"
)
GUARD = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(GUARD)


class PublicationGuardTest(unittest.TestCase):
    def test_private_path_classes_and_schema_exception(self):
        for path in [".env", "nested/.env.prod", "private/report.txt", "debug.log.1",
                     "bin/release.zip", "dump.sql", "customer.csv", "key.pem",
                     "doc/deployment-2026-01-01.md", ".gitlab-ci.yml", "sql/backup.sql",
                     "sql/customer.sql", "backup.tar.zst", "payload.phar"]:
            with self.subTest(path=path):
                self.assertTrue(GUARD.is_private_path(path))
        for path in ["sql/llx_kreawoo_site.sql", ".env.example", "LICENSE",
                     "class/client/kreawooClientApi.class.php"]:
            with self.subTest(path=path):
                self.assertFalse(GUARD.is_private_path(path))

    def test_rejects_archive_added_and_deleted_before_tip(self):
        with self.repo() as root:
            archive = root / "release.zip"
            archive.write_bytes(b"synthetic binary fixture")
            self.commit("Add archive")
            archive.unlink()
            self.commit("Remove archive")
            self.assertEqual(["release.zip"], GUARD.introduced_private_paths("--all"))

    def test_rejects_artifact_introduced_only_in_merge(self):
        with self.repo() as root:
            (root / "source.txt").write_text("base")
            self.commit("Base")
            self.git("checkout", "-b", "side")
            (root / "side.txt").write_text("side")
            self.commit("Side")
            self.git("checkout", "main")
            (root / "main.txt").write_text("main")
            self.commit("Main")
            self.git("merge", "--no-commit", "--no-ff", "side")
            (root / "business.csv").write_text("synthetic")
            self.commit("Merge with artifact")
            (root / "business.csv").unlink()
            self.commit("Remove artifact")
            self.assertEqual(["business.csv"], GUARD.introduced_private_paths("--all"))

    def test_rejects_option_injection(self):
        with self.assertRaises(ValueError):
            GUARD.introduced_private_paths("--output=unexpected")

    def repo(self):
        class RepositoryContext:
            def __enter__(inner):
                inner.temp = tempfile.TemporaryDirectory()
                inner.previous = Path.cwd()
                os.chdir(inner.temp.name)
                self.git("init", "--initial-branch=main")
                return Path(inner.temp.name)

            def __exit__(inner, *args):
                os.chdir(inner.previous)
                inner.temp.cleanup()

        return RepositoryContext()

    def git(self, *args):
        environment = dict(os.environ, GIT_AUTHOR_NAME="KreaWoo security test",
                           GIT_AUTHOR_EMAIL="test@example.invalid",
                           GIT_COMMITTER_NAME="KreaWoo security test",
                           GIT_COMMITTER_EMAIL="test@example.invalid")
        return subprocess.run(["git", "-c", "commit.gpgsign=false", *args], check=True,
                              stdout=subprocess.PIPE, stderr=subprocess.PIPE, env=environment)

    def commit(self, message):
        self.git("add", ".")
        self.git("commit", "-m", message)


if __name__ == "__main__":
    unittest.main()
