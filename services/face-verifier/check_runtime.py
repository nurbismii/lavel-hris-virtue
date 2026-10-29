"""Report package versions for cPanel diagnostics without importing AI engines."""
import json
import sys
from importlib.metadata import PackageNotFoundError, version

packages = {}
for name in ("deepface", "torch", "tensorflow", "numpy"):
    try:
        packages[name] = version(name)
    except PackageNotFoundError:
        packages[name] = None

print(json.dumps({"python": sys.version.split()[0], "packages": packages}))
