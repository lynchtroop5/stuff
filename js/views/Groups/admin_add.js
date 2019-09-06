$(document).ready(function() {
    $('#GroupCopyAgency').on('change', function() {
        var group = $(this).val();
        var groups = $('#GroupCopyGroupId');

        groups.empty();
        groups.append($('<option></option>')
            .attr('value', 0)
            .text('< None >'));

        if (!group) {
            return;
        }
        
        if (!(typeof permissionGroups[group] == 'undefined')) {
            $.each(permissionGroups[group].groups, function(key, value) {
                groups.append($('<option></option>')
                      .attr('value', key)
                      .text(value));
            })
        }
    });

});