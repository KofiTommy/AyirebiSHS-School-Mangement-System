(function () {
    'use strict';
    var service = document.getElementById('document-service');
    var delivery = document.getElementById('document-delivery');
    if (!service || !delivery) return;
    function update() {
        var option = service.options[service.selectedIndex];
        Array.prototype.forEach.call(delivery.options, function (item) {
            if (!item.value) return;
            item.disabled = !service.value || option.getAttribute('data-' + item.value) !== '1';
        });
        if (delivery.selectedIndex > 0 && delivery.options[delivery.selectedIndex].disabled) delivery.value = '';
        document.querySelectorAll('[data-delivery-fields]').forEach(function (field) {
            field.hidden = field.getAttribute('data-delivery-fields') !== delivery.value;
            field.querySelectorAll('input,textarea').forEach(function (input) { input.disabled = field.hidden; });
        });
        document.getElementById('service-description').textContent = option.getAttribute('data-description') || '';
        var total = document.getElementById('document-total');
        if (!service.value || !delivery.value) {
            total.textContent = 'Choose a service and delivery method to see the total.';
            return;
        }
        var base = Number(option.getAttribute('data-fee'));
        var extra = delivery.value === 'physical' ? Number(option.getAttribute('data-deliveryfee')) : 0;
        total.textContent = 'Total: GHS ' + ((base + extra) / 100).toFixed(2) + ' (service: GHS ' + (base / 100).toFixed(2) + ' · delivery: GHS ' + (extra / 100).toFixed(2) + '). Pay only after school approval.';
    }
    service.addEventListener('change', update);
    delivery.addEventListener('change', update);
    update();
}());
