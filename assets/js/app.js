/* Shared behaviour */
const csrfToken = document.querySelector('meta[name="csrf"]')?.content || '';
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

document.getElementById('menuBtn')?.addEventListener('click', () => document.getElementById('sidebar').classList.toggle('open'));
document.querySelectorAll('[data-autosubmit]').forEach((el) => el.addEventListener('change', () => el.form.submit()));
document.querySelectorAll('form[data-confirm]').forEach((form) =>
    form.addEventListener('submit', (ev) => { if (!confirm(form.dataset.confirm)) ev.preventDefault(); }));

async function postForm(url, data) {
    const body = new URLSearchParams(data);
    const res = await fetch(url, { method: 'POST', headers: { 'X-CSRF-Token': csrfToken }, body });
    return res.json().catch(() => ({ ok: false, message: 'Unexpected server response.' }));
}

/* My appointments: cancel */
document.querySelectorAll('[data-cancel]').forEach((btn) =>
    btn.addEventListener('click', async () => {
        if (!confirm('Cancel this appointment?')) return;
        btn.disabled = true;
        const out = await postForm('ajax/cancel-appointment.php', { id: btn.dataset.cancel });
        if (out.ok) location.reload();
        else { alert(out.message); btn.disabled = false; }
    }));

/* Booking dashboard */
if (document.getElementById('bookingApp')) {
    const state = { doctors: [], doctor: null, year: 0, month: 0, days: {}, date: null, time: null };
    const $ = (id) => document.getElementById(id);
    const pad = (n) => String(n).padStart(2, '0');
    const todayStr = (() => { const d = new Date(); return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`; })();
    const prettyDate = (s) => new Date(s + 'T00:00').toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
    const avatarHtml = (d, cls = '') => d.photo
        ? `<img class="avatar ${cls}" src="${esc(d.photo)}" alt="">`
        : `<span class="avatar ${cls}" style="background:${esc(d.color)}">${esc(d.initials)}</span>`;

    let searchTimer;
    ['searchInput'].forEach((id) => $(id).addEventListener('input', () => { clearTimeout(searchTimer); searchTimer = setTimeout(loadDoctors, 300); }));
    $('specSelect').addEventListener('change', loadDoctors);

    async function loadDoctors() {
        $('doctorList').innerHTML = '<p class="muted pad">Loading doctors…</p>';
        const qs = new URLSearchParams({ q: $('searchInput').value.trim(), spec: $('specSelect').value });
        const res = await fetch('ajax/search-doctors.php?' + qs);
        if (res.status === 401) { location.href = 'login.php'; return; }
        const data = await res.json();
        state.doctors = data.doctors;
        renderDoctors(data.recommend);
        if (!state.doctors.length) { state.doctor = null; renderProfile(); return; }
        if (!state.doctor || !state.doctors.some((d) => d.id === state.doctor.id)) selectDoctor(state.doctors[0].id);
        else renderDoctors(data.recommend);
    }

    function renderDoctors(recommend) {
        const note = $('recoNote');
        note.hidden = !(recommend && state.doctors.some((d) => d.rank));
        note.innerHTML = 'Ranked by next free slot, experience, number of open slots and fee. Doctors with no free slots in the next 14 days are listed last.';
        if (!state.doctors.length) { $('doctorList').innerHTML = '<p class="muted pad">No doctors match your search.</p>'; return; }
        $('doctorList').innerHTML = state.doctors.map((d) => `
            <button type="button" class="doctor ${state.doctor && state.doctor.id === d.id ? 'selected' : ''}" data-id="${d.id}">
                ${avatarHtml(d)}
                <span class="doctor-info">
                    <strong>${esc(d.name)}</strong>
                    <span class="muted">${esc(d.specialization)} · ${d.experience} yrs · ${esc(d.fee)}</span>
                    <span class="next ${d.next_slot ? '' : 'none'}">${d.next_slot ? 'Next: ' + esc(d.next_slot) : 'No free slots in 14 days'}</span>
                    ${recommend && d.rank ? `<span class="reco"><span class="rank">${d.rank === 1 ? 'Best match' : '#' + d.rank}</span> ${esc(d.reasons.slice(0, 3).join(' · '))} <em>score ${d.score}</em></span>` : ''}
                </span>
            </button>`).join('');
        $('doctorList').querySelectorAll('.doctor').forEach((el) => el.addEventListener('click', () => selectDoctor(+el.dataset.id)));
    }

    async function selectDoctor(id) {
        state.doctor = state.doctors.find((d) => d.id === id);
        state.date = state.time = null;
        const start = state.doctor.next_date ? new Date(state.doctor.next_date + 'T00:00') : new Date();
        state.year = start.getFullYear();
        state.month = start.getMonth() + 1;
        document.querySelectorAll('.doctor').forEach((el) => el.classList.toggle('selected', +el.dataset.id === id));
        renderProfile();
        await loadMonth();
        if (state.doctor.next_date) selectDate(state.doctor.next_date);
        else { $('slots').innerHTML = '<p class="muted">No free slots in the next 14 days. Try browsing other months.</p>'; $('slotsDate').textContent = ''; }
    }

    async function loadMonth() {
        const res = await fetch(`ajax/get-availability.php?doctor_id=${state.doctor.id}&month=${state.year}-${pad(state.month)}`);
        state.days = (await res.json()).days || {};
        renderCalendar();
    }

    function renderCalendar() {
        $('monthLabel').textContent = new Date(state.year, state.month - 1, 1).toLocaleDateString('en-GB', { month: 'long', year: 'numeric' });
        const first = new Date(state.year, state.month - 1, 1);
        const offset = (first.getDay() + 6) % 7; // Monday first
        const total = new Date(state.year, state.month, 0).getDate();
        let html = '<span></span>'.repeat(offset);
        for (let d = 1; d <= total; d++) {
            const ds = `${state.year}-${pad(state.month)}-${pad(d)}`;
            const info = state.days[ds];
            let cls = 'off', disabled = true;
            if (ds < todayStr) cls = 'past';
            else if (info && info.open > 0) { cls = 'open'; disabled = false; }
            else if (info) cls = 'full';
            if (ds === state.date) cls += ' selected';
            if (ds === todayStr) cls += ' today';
            html += `<button type="button" class="day ${cls}" data-date="${ds}" ${disabled ? 'disabled' : ''}>${d}</button>`;
        }
        $('calGrid').innerHTML = html;
        $('calGrid').querySelectorAll('.day:not([disabled])').forEach((el) => el.addEventListener('click', () => selectDate(el.dataset.date)));
    }

    function shiftMonth(step) {
        const d = new Date(state.year, state.month - 1 + step, 1);
        state.year = d.getFullYear(); state.month = d.getMonth() + 1;
        if (state.doctor) loadMonth(); else renderCalendar();
    }
    $('prevMonth').addEventListener('click', () => shiftMonth(-1));
    $('nextMonth').addEventListener('click', () => shiftMonth(1));

    async function selectDate(date) {
        state.date = date; state.time = null;
        renderCalendar(); renderProfile();
        $('slotsDate').textContent = prettyDate(date);
        $('slots').innerHTML = '<p class="muted">Loading…</p>';
        const res = await fetch(`ajax/get-availability.php?doctor_id=${state.doctor.id}&date=${date}`);
        const slots = (await res.json()).slots || [];
        if (!slots.length) { $('slots').innerHTML = '<p class="muted">The doctor does not work on this day.</p>'; return; }
        $('slots').innerHTML = slots.map((s) => `<button type="button" class="slot ${s.status}" data-time="${s.time}" ${s.status !== 'available' ? 'disabled' : ''} title="${s.status === 'booked' ? 'Already booked' : s.status === 'past' ? 'Time has passed' : 'Available'}">${esc(s.label)}</button>`).join('');
        $('slots').querySelectorAll('.slot.available').forEach((el) => el.addEventListener('click', () => {
            state.time = el.dataset.time;
            $('slots').querySelectorAll('.slot').forEach((b) => b.classList.toggle('selected', b === el));
            renderProfile();
        }));
    }

    function renderProfile() {
        const d = state.doctor, box = $('profile');
        if (!d) { box.innerHTML = '<p class="muted">No doctor selected.</p>'; return; }
        const concerns = $('concerns')?.value || '';
        const when = state.date && state.time ? `${prettyDate(state.date)} at ${state.time}` : 'Choose a date and a time slot';
        box.innerHTML = `
            <div class="profile-top">${avatarHtml(d, 'lg')}<div><h2>${esc(d.name)}</h2><p class="spec">${esc(d.specialization)}</p><p class="muted">${esc(d.qualification || '')}</p></div></div>
            <div class="facts"><div><strong>${d.experience}</strong><span>Years experience</span></div><div><strong>${esc(d.fee)}</strong><span>Consultation</span></div><div><strong>${d.slot_minutes} min</strong><span>Per visit</span></div></div>
            <p class="bio">${esc(d.bio || '')}</p>
            <h3>Weekly schedule</h3>
            <ul class="week">${d.schedule.map((s) => `<li><span>${esc(s.day)}</span><span>${esc(s.start)} – ${esc(s.end)}</span></li>`).join('') || '<li class="muted">No schedule set.</li>'}</ul>
            <div class="book-box">
                <div class="chosen ${state.time ? 'ready' : ''}">${esc(when)}</div>
                <label>Your concerns / symptoms<textarea id="concerns" rows="3" maxlength="500" placeholder="Describe briefly why you want to see the doctor">${esc(concerns)}</textarea></label>
                <div id="formMsg" class="form-msg" hidden></div>
                <button type="button" class="btn btn-primary btn-block" id="bookBtn" ${state.time ? '' : 'disabled'}>Confirm booking</button>
            </div>`;
        $('bookBtn').addEventListener('click', book);
    }

    async function book() {
        const msg = $('formMsg'), btn = $('bookBtn');
        msg.hidden = true; btn.disabled = true; btn.textContent = 'Booking…';
        const out = await postForm('ajax/book-appointment.php', { doctor_id: state.doctor.id, date: state.date, time: state.time, concerns: $('concerns').value });
        btn.textContent = 'Confirm booking';
        if (!out.ok) {
            msg.textContent = out.message; msg.hidden = false; btn.disabled = false;
            if (state.date) { const t = state.time; state.time = null; await selectDate(state.date); renderProfile(); $('formMsg').textContent = out.message; $('formMsg').hidden = false; }
            return;
        }
        $('confRef').textContent = out.reference;
        $('confDetails').innerHTML = `<dt>Doctor</dt><dd>${esc(out.doctor)} (${esc(out.specialization)})</dd><dt>Date</dt><dd>${esc(out.date)}</dd><dt>Time</dt><dd>${esc(out.time)}</dd><dt>Fee</dt><dd>${esc(out.fee)}</dd><dt>Status</dt><dd>${esc(out.status)}</dd>`;
        $('confirmModal').hidden = false;
        state.time = null;
        await loadMonth();
        await selectDate(state.date);
    }
    $('closeModal').addEventListener('click', () => { $('confirmModal').hidden = true; loadDoctors(); });

    renderCalendar();
    loadDoctors();
}
