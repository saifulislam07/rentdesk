import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';

const element = document.getElementById('calendar');
const productFilter = document.getElementById('product_id');

const calendar = new Calendar(element, {
    plugins: [dayGridPlugin, timeGridPlugin],
    initialView: 'dayGridMonth',
    timeZone: 'UTC',
    height: 'auto',
    nextDayThreshold: '00:00:00',
    headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek',
    },
    eventSources: [
        {
            url: element.dataset.eventsUrl,
            extraParams: () => (productFilter.value ? { product_id: productFilter.value } : {}),
        },
    ],
    eventDidMount: ({ event, el }) => {
        el.title = `${event.title} (${event.extendedProps.rental})`;
    },
});

calendar.render();
productFilter.addEventListener('change', () => calendar.refetchEvents());
