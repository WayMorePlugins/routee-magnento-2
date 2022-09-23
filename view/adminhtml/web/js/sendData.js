
requirejs(['jquery'], function($){
    let count = 1;
    let action = '';

    $('.send_mass_data').on("click", function (e) {
        $('.send_mass_data').attr("disabled", "true");
        action = $(this).attr("data-action");
        make_ajax({action : action, cycle_count : count});
    })

    function make_ajax(data) {
        $.ajax({
            type: "POST",
            url: url,
            data: data,
            dataType: 'json',
        }).fail(function (jqXHR, textStatus, errorThrown) {
            console.log('error ' + textStatus);
        }).done(function (data, textStatus, jqXHR) {
            process_result(data)
        });
    }

    function process_result(data) {
        count++;
        if (data.reload === 1){
            location.reload();
        } else if (data.reload === 0){
            let req = {
                action : action,
                cycle_count : count
            };
            make_ajax(req);
        }
    }
});