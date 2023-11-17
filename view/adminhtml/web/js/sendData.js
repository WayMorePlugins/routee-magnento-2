
requirejs(['jquery'], function($){
    let count = 1;
    let action = '';

    $('.send_mass_data').on("click", function (e) {
        $('.send_mass_data').attr("disabled", "true");
        action = $(this).attr("data-action");
        make_ajax(url, {action : action, cycle_count : count, form_key: window.FORM_KEY});
    })

    $("#routee-log-export-api").on("click", function (e) {
        e.preventDefault();
        $(this).attr("disabled", "true");
        var bulklogaction = $(this).attr("data-action");
        var bulklogurl = $(this).attr("data-url");
        var bulklogdata = {'action' :bulklogaction, cycle_count : count};
        make_ajax(bulklogurl, bulklogdata);
    })

    /**
     * @param  {apiurl}
     * @param  {data}
     * @return {void}
     */
    function make_ajax(apiurl, data) {
        $('body').trigger('processStart');
        $.ajax({
            type: "POST",
            url: apiurl,
            data: data,
            dataType: 'json',
            timeout : 0,
        }).done(function (data, textStatus, jqXHR) {
            $('body').trigger('processStop');
            process_result(data)
        }).fail(function (jqXHR, textStatus, errorThrown) {
            console.log('error ' + textStatus);
            $('body').trigger('processStop');
        });
    }

    /**
     * @param  {data}
     * @return {void}
     */
    function process_result(data) {
        count++;
        if (data.reload === 1){
            location.reload();
        } else if (data.reload === 0){
            let req = {
                action : action,
                cycle_count : count,
				form_key: window.FORM_KEY
            };

            make_ajax(url, req);
        }
    }
});
