document.addEventListener('DOMContentLoaded', function () {
    if (!document.body || !document.body.classList.contains('academic-plan-page')) {
        return;
    }

    var form = document.querySelector('.academic-plan-form-card form');
    var termSelect = form ? form.querySelector('select[name="termname"]') : null;
    if (!termSelect || form.querySelector('[name="weeknumber"]')) {
        return;
    }

    var field = document.createElement('div');
    field.className = 'academic-plan-week-field';
    field.innerHTML = '<label for="academic-plan-weeknumber">Teaching Week <small>(optional)</small></label><input id="academic-plan-weeknumber" type="number" min="1" max="30" name="weeknumber" placeholder="e.g. Week 1 = 1">';
    var dateGrid = form.querySelector('.academic-plan-form-grid');
    form.insertBefore(field, form.firstChild);
    if (dateGrid) {
        form.insertBefore(dateGrid, field.nextSibling);
    }

    var table = document.querySelector('.academic-plan-table');
    if (!table) { return; }
    fetch('academic-plan-week-data.php', { credentials: 'same-origin' }).then(function (response) { return response.json(); }).then(function (activities) {
        var lookup = {};
        activities.forEach(function (activity) { lookup[activity.title + '|' + activity.startdate] = activity.weeknumber; });
        var headerRow = table.querySelector('thead tr');
        if (headerRow && !headerRow.querySelector('.academic-plan-week-heading')) {
            var heading = document.createElement('th'); heading.className = 'academic-plan-week-heading'; heading.textContent = 'Week'; headerRow.insertBefore(heading, headerRow.firstChild);
        }
        table.querySelectorAll('tbody tr').forEach(function (row) {
            var cells = row.querySelectorAll('td'); if (cells.length < 3) { return; }
            var startText = cells[0].textContent.trim(); var title = cells[2].querySelector('strong') ? cells[2].querySelector('strong').textContent.trim() : '';
            var match = activities.filter(function (activity) { return activity.title === title && new Date(activity.startdate).toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'}) === startText; })[0];
            var cell = document.createElement('td'); cell.className = 'academic-plan-week-cell'; cell.textContent = match && match.weeknumber > 0 ? 'Week ' + match.weeknumber : '—'; row.insertBefore(cell, row.firstChild);
            if (match) {
                var actionCell = row.querySelector('td:last-child');
                if (actionCell && !actionCell.querySelector('.academic-plan-edit')) {
                    var edit = document.createElement('a'); edit.className = 'academic-plan-edit'; edit.href = 'academic-plan.php?edit=' + encodeURIComponent(match.planid); edit.textContent = 'Edit'; actionCell.insertBefore(edit, actionCell.firstChild);
                }
            }
        });
        var editId = new URLSearchParams(window.location.search).get('edit');
        if (editId) {
            var current = activities.filter(function (activity) { return activity.planid === editId; })[0];
            if (current) {
                ['title','eventtype','startdate','enddate','description','batchid','termname','weeknumber'].forEach(function (name) { var input=form.querySelector('[name="'+name+'"]'); if(input){ input.value=current[name] || ''; } });
                var hidden=document.createElement('input'); hidden.type='hidden'; hidden.name='planid'; hidden.value=current.planid; form.appendChild(hidden);
                var button=form.querySelector('[name="save_plan"]'); if(button){ button.innerHTML='<i class="fa fa-save"></i> Update Activity'; }
                window.scrollTo({top: form.getBoundingClientRect().top + window.pageYOffset - 70, behavior: 'smooth'});
            }
        }
    }).catch(function () {});
});
