from typing import Any


class LLMProvider:
    def __init__(self, provider: str, api_key: str, model: str):
        self.provider, self.api_key, self.model = provider, api_key, model

    def answer(self, question: str, context: list[dict[str, str]]) -> str:
        if self.provider == "mock":
            if not context:
                return "I could not find that in the approved healthcare information. Please contact the clinic."
            return "Based on the approved information: " + " ".join(item["text"] for item in context)
        if self.provider == "openai":
            from openai import OpenAI
            client = OpenAI(api_key=self.api_key)
            source_text = "\n\n".join(item["text"] for item in context)
            response = client.chat.completions.create(
                model=self.model,
                temperature=0.1,
                messages=[
                    {"role": "system", "content": "Answer only from the supplied context. Do not diagnose or prescribe. Escalate emergencies."},
                    {"role": "user", "content": f"Context:\n{source_text}\n\nQuestion: {question}"},
                ],
            )
            return response.choices[0].message.content or "I could not generate an answer."
        raise ValueError(f"Unsupported LLM_PROVIDER: {self.provider}")

