from collections.abc import Sequence


class EmbeddingService:
    def __init__(self, model_name: str):
        self._model = None
        self._model_name = model_name

    def embed(self, texts: Sequence[str]) -> list[list[float]]:
        if self._model is None:
            from sentence_transformers import SentenceTransformer
            self._model = SentenceTransformer(self._model_name)
        return self._model.encode(list(texts), normalize_embeddings=True).tolist()

