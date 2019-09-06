$(document).ready(function() {
    $('#FormGroupCopyAgency').on('change', function() {
        var group = $(this).val();
        var groups = $('#FormGroupCopyFormGroupId');

        groups.empty();
        groups.append($('<option></option>')
            .attr('value', 0)
            .text('< None >'));

        if (!group) {
            return;
        }
        
        if (!(typeof formGroups[group] == 'undefined')) {
            $.each(formGroups[group].groups, function(key, value) {
                groups.append($('<option></option>')
                      .attr('value', key)
                      .text(value));
            })
        }
    });

});