import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import listPlugin from '@fullcalendar/list';
import interactionPlugin from '@fullcalendar/interaction';

// Walang @fullcalendar/timegrid dito. Inalis ang timeGridWeek na tab sa
// v7.45 — all-day ang bawat event na ipinapadala ng events(), kaya
// permanenteng blangko ang oras-oras na grid. Ang bundle lang ang natitirang
// gastos kapag naiwan ang import.
window.FullCalendar = { Calendar, dayGridPlugin, listPlugin, interactionPlugin };
