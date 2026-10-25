from pathlib import Path
import os
import subprocess
import tempfile
import zipfile

root = Path(__file__).resolve().parents[1]
archive = root / 'docs/downloads/Vitovent300F-Kachel-1.1.zip'
with tempfile.TemporaryDirectory() as directory:
    with zipfile.ZipFile(archive) as z:
        assert z.testzip() is None
        assert all(not Path(n).is_absolute() and '..' not in Path(n).parts for n in z.namelist())
        z.extractall(directory)
    subprocess.run([os.environ.get('PHP_BIN', 'php'), str(root / 'tests/install_test.php'),
                    str(Path(directory) / 'Vitovent300F_Kachel')], check=True)

# Regression: Module Control imports the full repository, not just the minimal ZIP.
# This catches root-level artifact folders accidentally treated as modules.
subprocess.run([os.environ.get('PHP_BIN', 'php'), str(root / 'tests/install_test.php'),
                str(root)], check=True)
