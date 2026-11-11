from fastapi import APIRouter, Depends, Request

from backend.models import ChatRequest, ChatResponse
from backend.rate_limit import enforce_rate_limit
from backend.security import reject_prompt_injection, require_api_key
def router_for() -> APIRouter:
    router = APIRouter()

    @router.post("/chat", response_model=ChatResponse, dependencies=[Depends(require_api_key)])
    def chat(request: Request, payload: ChatRequest) -> ChatResponse:
        enforce_rate_limit(request)
        reject_prompt_injection(payload.message)
        answer, sources = request.app.state.pipeline.answer(payload.message)
        return ChatResponse(answer=answer, sources=sources)

    return router
