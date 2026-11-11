import re
from fastapi import Header, HTTPException

from config.settings import get_settings


INJECTION_PATTERNS = (
    r"ignore\s+(?:all\s+)?(?:previous\s+|prior\s+)?instructions",
    r"system\s+prompt",
    r"reveal\s+(your|the)\s+(prompt|instructions)",
)


def require_api_key(x_api_key: str | None = Header(default=None)) -> None:
    expected = get_settings().ai_api_key
    if not expected or expected == "change-this-local-api-key":
        raise HTTPException(status_code=503, detail="Configure AI_API_KEY before starting the service.")
    if x_api_key != expected:
        raise HTTPException(status_code=401, detail="Unauthorized.")


def reject_prompt_injection(message: str) -> None:
    if any(re.search(pattern, message, re.IGNORECASE) for pattern in INJECTION_PATTERNS):
        raise HTTPException(status_code=400, detail="That instruction cannot be processed.")
