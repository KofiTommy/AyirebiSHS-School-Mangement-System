document.addEventListener('DOMContentLoaded', function () {
    var pageSelects = document.querySelectorAll('.gc-field select');
    if (pageSelects.length) {
        var updateSelectState = function (select) {
            select.classList.toggle('is-empty', select.value === '');
        };

        pageSelects.forEach(function (select) {
            select.addEventListener('change', function () {
                updateSelectState(select);
            });
            updateSelectState(select);
        });
    }

    var forms = document.querySelectorAll('[data-action-form]');
    if (!forms.length) {
        return;
    }

    forms.forEach(function (form) {
        var actionSelect = form.querySelector('[data-action-select]');
        var actionNote = form.querySelector('[data-action-note]');
        var submitButton = form.querySelector('[data-action-submit]');
        var rescheduleFields = form.querySelectorAll('[data-reschedule-only] input, [data-reschedule-only] select, [data-reschedule-only] textarea');

        if (!actionSelect || !actionNote || !submitButton) {
            return;
        }

        var updateState = function () {
            var isCancel = actionSelect.value === 'cancel';
            form.classList.toggle('is-cancel-mode', isCancel);

            rescheduleFields.forEach(function (field) {
                field.disabled = isCancel;
            });

            if (isCancel) {
                actionNote.innerHTML = 'You are about to <strong>cancel this appointment</strong>. A new date and time are not required.';
                submitButton.classList.remove('gc-btn--secondary');
                submitButton.classList.add('gc-btn--danger');
                submitButton.innerHTML = '<i class="fa fa-times-circle"></i> Cancel Appointment';
                return;
            }

            actionNote.innerHTML = 'Choose <strong>Request Another Day</strong> to suggest a new meeting time. Choose <strong>Cancel This Appointment</strong> to close this appointment.';
            submitButton.classList.remove('gc-btn--danger');
            submitButton.classList.add('gc-btn--secondary');
            submitButton.innerHTML = '<i class="fa fa-calendar-times-o"></i> Send Appointment Request';
        };

        actionSelect.addEventListener('change', updateState);
        updateState();
    });
});
