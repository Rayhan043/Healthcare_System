from datetime import date, datetime, timedelta

from backend.models import DoctorSearchRequest
from config.settings import get_settings


def _connection():
    import mysql.connector
    settings = get_settings()
    return mysql.connector.connect(
        host=settings.mysql_host, port=settings.mysql_port, database=settings.mysql_database,
        user=settings.mysql_user, password=settings.mysql_password,
    )


def find_doctors(request: DoctorSearchRequest) -> list[dict]:
    target = request.appointment_date or date.today()
    end = target + timedelta(days=14)
    connection = _connection()
    try:
        cursor = connection.cursor(dictionary=True)
        cursor.execute(
            """SELECT d.id, d.name, s.name AS specialization, d.experience_years,
                      d.fee, d.slot_minutes
               FROM doctors d JOIN specializations s ON s.id = d.specialization_id
               WHERE d.active = 1 AND LOWER(s.name) LIKE LOWER(%s)
               ORDER BY d.experience_years DESC, d.fee ASC""",
            (f"%{request.specialization}%",),
        )
        doctors = cursor.fetchall()
        for doctor in doctors:
            cursor.execute(
                """SELECT da.weekday, da.start_time, da.end_time
                   FROM doctor_availability da
                   WHERE da.doctor_id = %s""",
                (doctor["id"],),
            )
            schedules = cursor.fetchall()
            cursor.execute(
                """SELECT appointment_date, appointment_time FROM appointments
                   WHERE doctor_id = %s AND appointment_date BETWEEN %s AND %s
                   AND status <> 'cancelled'""",
                (doctor["id"], target, end),
            )
            booked = {(row["appointment_date"], row["appointment_time"]) for row in cursor.fetchall()}
            doctor["available_days"] = _available_days(target, schedules, booked, doctor["slot_minutes"])
            doctor["next_available"] = doctor["available_days"][0] if doctor["available_days"] else None
        return sorted(doctors, key=lambda doctor: (doctor["next_available"] is None, doctor["next_available"] or ""))
    finally:
        connection.close()


def _available_days(start: date, schedules: list[dict], booked: set, minutes: int) -> list[str]:
    result = []
    for offset in range(15):
        current = start + timedelta(days=offset)
        matching = [item for item in schedules if item["weekday"] == current.isoweekday()]
        if matching and any(datetime.combine(current, item["start_time"]).time() not in {time for day, time in booked if day == current} for item in matching):
            result.append(current.isoformat())
    return result
