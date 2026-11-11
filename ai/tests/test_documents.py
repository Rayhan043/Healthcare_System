from pathlib import Path

from rag.chunker import chunk_text
from rag.document_loader import load_documents


def test_documents_load_and_chunk():
    documents = load_documents(Path(__file__).parents[1] / "documents")
    assert documents
    assert chunk_text(documents[0][1])

