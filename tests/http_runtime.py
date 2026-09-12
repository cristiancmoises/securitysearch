"""Local controller-audit prerequisite and status diagnostics; no providers."""
import re
import subprocess

SUCCESS = b"Native PHP audit extensions present. No external requests performed.\n"


class RuntimePrerequisiteError(RuntimeError):
    pass


def require_native_runtime(root):
    """Do not launch PHP worker fixtures when mandatory native modules are absent."""
    command = ["php", "-d", "apc.enable_cli=1", str(root / "tests/native-runtime.php")]
    try:
        result = subprocess.run(command, cwd=root, capture_output=True, timeout=15)
    except subprocess.TimeoutExpired:
        raise RuntimePrerequisiteError("HTTP_AUDIT_PREREQUISITE: PHP check timed out") from None
    except OSError:
        raise RuntimePrerequisiteError("HTTP_AUDIT_PREREQUISITE: PHP check could not start") from None
    if result.returncode != 0:
        match = re.fullmatch(rb"NATIVE_RUNTIME_MISSING: ([a-z0-9_, ]+)\r?\n?", result.stderr)
        detail = match.group(0).decode("ascii").strip() if match else "native check failed; exit=" + str(result.returncode)
        raise RuntimePrerequisiteError("HTTP_AUDIT_PREREQUISITE: " + detail)
    if result.stdout != SUCCESS or result.stderr:
        raise RuntimePrerequisiteError("HTTP_AUDIT_PREREQUISITE: unexpected PHP check response")


def require_status(actual, expected, label):
    """Only fixed fixture labels are accepted; no response bodies or query URLs."""
    if label not in ("reset", "images", "append"):
        raise ValueError("unknown controller label")
    if actual != expected:
        raise AssertionError("HTTP_CONTROLLER_FAILURE: " + label + "; expected=" + str(expected)
                             + " actual=" + str(actual) + "; inspect local PHP diagnostics")
