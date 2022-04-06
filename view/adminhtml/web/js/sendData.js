
requirejs(['jquery'], function($){
    //IF WE NEED TO CHANGE THE TESTMODE FIELD VALUE TO DEBUG THEN HAVE TO COMMENT THIS BELOW LINE NO 4 ON 21ST JAN 2020
    $('#row_waymoreroutee_general_testmode').attr('style','display:none;');
    $('#row_waymoreroutee_general_datatransferred').attr('style','display:none;');
    var dtransferred = $('#waymoreroutee_general_datatransferred').val();
    var getuuid = $('#send_mass_data').attr('onclick');

    if(getuuid==dtransferred) {
        $('#waymoreroutee_general_username').attr('readonly','readonly');
        $('#waymoreroutee_general_password').attr('readonly','readonly');
        $('#send_mass_data').removeAttr('onclick');
        $('#send_mass_data').attr('disabled','disabled');
        $('#send_mass_data span').text('Synced with Routee');
        $('#waymoreroutee_general-head').before('<div class="message message-success success"><div data-ui-id="messages-message-success">You have been synchronized with waymore.routee.net.</div></div>');
    }
});

/**
 * @param sendDataUrl
 */
function sendMassDataFunc(sendDataUrl) {
    if(sendDataUrl) {
        var param = 'ajax=1';
        jQuery.ajax({
            showLoader: true,
            url: sendDataUrl,
            data: param,
            type: "POST",
            dataType: 'json'
        }).done(function (data) {
            if(data!==null) {
                if(data.msg=='IntegrationDone') {
                    jQuery('#waymoreroutee_general_username').attr('readonly','readonly');
                    jQuery('#waymoreroutee_general_password').attr('readonly','readonly');
                    jQuery('#send_mass_data').removeAttr('onclick');
                    jQuery('#send_mass_data').attr('disabled','disabled');
                    jQuery('#send_mass_data span').text('Synced with Routee');
                    location.reload(true);
                }
            }
        });
    }
}