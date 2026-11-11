from fastapi import APIRouter, Depends

from api.doctors import find_doctors
from backend.models import BookingAction, DoctorSearchRequest
from backend.security import require_api_key


router = APIRouter()


@router.post("/doctors/recommend", dependencies=[Depends(require_api_key)])
def recommend(payload: DoctorSearchRequest) -> dict:
    doctors = find_doctors(payload)
    return {"doctors": doctors, "write_performed": False}


@router.post("/appointments/preview", dependencies=[Depends(require_api_key)])
def preview(payload: BookingAction) -> dict:
    if payload.action != "preview":
        return {"error": "Only preview is supported.", "write_performed": False}
    if not payload.intent.specialization:
        return {"error": "A specialization is required.", "write_performed": False}
    choices = find_doctors(DoctorSearchRequest(
        specialization=payload.intent.specialization,
        appointment_date=payload.intent.appointment_date,
        preferred_time=payload.intent.preferred_time,
    ))
    return {
        "message": "Review a choice in the existing PHP booking page before booking.",
        "doctors": choices,
        "write_performed": False,
        "php_booking_authority": True,
    }

