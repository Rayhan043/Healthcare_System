from collections import defaultdict, deque
from time import monotonic

from fastapi import HTTPException, Request


_requests: dict[str, deque[float]] = defaultdict(deque)


def enforce_rate_limit(request: Request, limit: int = 30, window: int = 60) -> None:
    now = monotonic()
    key = request.client.host if request.client else "unknown"
    bucket = _requests[key]
    while bucket and now - bucket[0] > window:
        bucket.popleft()
    if len(bucket) >= limit:
        raise HTTPException(status_code=429, detail="Too many requests. Try again shortly.")
    bucket.append(now)

