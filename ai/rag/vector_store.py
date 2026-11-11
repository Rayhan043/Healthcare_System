from pathlib import Path

from rag.chunker import chunk_text
from rag.document_loader import load_documents
from rag.embeddings import EmbeddingService


class VectorStore:
    def __init__(self, directory: Path, embedding_service: EmbeddingService):
        import chromadb
        self._client = chromadb.PersistentClient(path=str(directory))
        self._collection = self._client.get_or_create_collection("healthcare_knowledge")
        self._embeddings = embedding_service

    def ingest(self, documents_dir: Path) -> int:
        ids, texts, metadatas = [], [], []
        for filename, text in load_documents(documents_dir):
            for index, chunk in enumerate(chunk_text(text)):
                ids.append(f"{filename}:{index}")
                texts.append(chunk)
                metadatas.append({"source": filename})
        if not texts:
            return 0
        self._collection.upsert(
            ids=ids,
            documents=texts,
            metadatas=metadatas,
            embeddings=self._embeddings.embed(texts),
        )
        return len(texts)

    def search(self, query: str, limit: int = 4) -> list[dict[str, str]]:
        result = self._collection.query(
            query_embeddings=self._embeddings.embed([query]),
            n_results=limit,
        )
        return [
            {"text": text, "source": metadata["source"]}
            for text, metadata in zip(result["documents"][0], result["metadatas"][0])
        ]

