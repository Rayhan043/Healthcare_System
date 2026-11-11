from datetime import date
from typing import Any, Literal

from pydantic import BaseModel, Field


class ChatRequest(BaseModel):
    message: str = Field(min_length=1, max_length=2000)


class ChatResponse(BaseModel):
    answer: str
    sources: list[str] = []
    doctors: list[dict[str, Any]] = []
    booking_options: list[dict[str, Any]] = []


class DoctorSearchRequest(BaseModel):
    specialization: str = Field(min_length=2, max_length=60)
    appointment_date: date | None = None
    preferred_time: str | None = Field(default=None, max_length=5)


class BookingIntent(BaseModel):
    specialization: str | None = None
    appointment_date: date | None = None
    preferred_time: str | None = None
    confirmed: bool = False


class BookingAction(BaseModel):
    action: Literal["preview"] = "preview"
    intent: BookingIntent

