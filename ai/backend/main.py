from contextlib import asynccontextmanager

from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware

from api.appointments import router as appointment_router
from api.chat import router_for
from api.health import router as health_router
from config.settings import get_settings
from rag.pipeline import RAGPipeline


@asynccontextmanager
async def lifespan(app: FastAPI):
    settings = get_settings()
    app.state.pipeline = RAGPipeline(settings)
    yield


settings = get_settings()
app = FastAPI(title="Healthcare AI Assistant", lifespan=lifespan)
app.add_middleware(CORSMiddleware, allow_origins=settings.origins, allow_methods=["GET", "POST"], allow_headers=["*"])
app.include_router(health_router)
app.include_router(appointment_router, prefix="/api")
app.include_router(router_for(), prefix="/api")
