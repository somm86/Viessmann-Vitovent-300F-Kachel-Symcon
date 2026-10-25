from pathlib import Path
import os
import subprocess
import tempfile
import zipfile

root = Path(__file__).resolve().parents[1]
archive = root / 'dist/Vitovent300F-Kachel-1.1.zip'
with tempfile.TemporaryDirectory() as directory:
    with zipfile.ZipFile(archive) as z:
        assert z.testzip() is None
        assert all(not Path(n).is_absolute() and '..' not in Path(n).parts for n in z.namelist())
        z.extractall(directory)
    subprocess.run([os.environ.get('PHP_BIN', 'php'), str(root / 'tests/install_test.php'),
                    str(Path(directory) / 'Vitovent300F_Kachel')], check=True)
