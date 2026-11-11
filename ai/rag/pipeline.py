from pathlib import Path

from config.settings import Settings
from rag.embeddings import EmbeddingService
from rag.llm_provider import LLMProvider
from rag.vector_store import VectorStore


class RAGPipeline:
    def __init__(self, settings: Settings):
        self.store = VectorStore(settings.root_dir / "vector_store", EmbeddingService(settings.embedding_model))
        self.llm = LLMProvider(settings.llm_provider, settings.llm_api_key, settings.llm_model)
        self.documents_dir = settings.root_dir / "documents"

    def ingest(self) -> int:
        return self.store.ingest(self.documents_dir)

    def answer(self, question: str) -> tuple[str, list[str]]:
        context = self.store.search(question)
        return self.llm.answer(question, context), sorted({item["source"] for item in context})

