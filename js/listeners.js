jQuery(document).ready(function()
{
    // Alert messages on form submit *******************************************
    jQuery('form#UmanITUpdateURLs_runquery_form').submit(function(e)
    {
        var checkbox = jQuery(this).find('#UmanITUpdateURLs_runquery_checkbox');

        if (checkbox.is(':checked'))
        {
            var confirmationMsg = jQuery(this).find('#UmanITUpdateURLs_runquery_submit').attr('title');

            if (!confirm(confirmationMsg))
            {
                e.preventDefault();
            }
        }
        else
        {
            var alertMsg = checkbox.attr('title');

            alert(alertMsg);
            e.preventDefault();
        }
    });

    // Enhanced new url fields *************************************************
    jQuery('#UmanITUpdateURLs_newurl, #UmanITUpdateURLs_runquery_newurl').live('input', function()
    {
        // Update the other field
        var fieldToUpdate = null;
        
        switch (jQuery(this).attr('id'))
        {
            case 'UmanITUpdateURLs_newurl':
                fieldToUpdate = jQuery('#UmanITUpdateURLs_runquery_newurl');
                break;
            case 'UmanITUpdateURLs_runquery_newurl':
                fieldToUpdate = jQuery('#UmanITUpdateURLs_newurl');
                break;
        }
        
        fieldToUpdate.val(jQuery(this).val());
        
        // Update the URL in the runquery field
        var minWidth = jQuery('#UmanITUpdateURLs_newurl').css('min-width').replace(/[^-\d\.]/g, '');
        var charWidth = 6.68;

        jQuery('#UmanITUpdateURLs_runquery_newurl').width(Math.max(charWidth * jQuery(this).val().length, minWidth));
    });
    
    // Select all option for checkboxes ****************************************
    var selectAll  = jQuery('#select-all');
    var checkboxes = selectAll.closest('fieldset').find(':checkbox').not(selectAll);
    
    jQuery('.select-all').css('display', 'inline-block');
    
    selectAll.change(function()
    {
        checkboxes.prop('checked', jQuery(this).prop('checked'))
    });
    
    // If all of them are checked
    if (selectAll.closest('fieldset').find(':checkbox:checked').not(selectAll).length == checkboxes.length)
    {
        selectAll.prop('checked', true);
    }
    
    checkboxes.change(function()
    {
        if (!jQuery(this).prop('checked'))
        {
            selectAll.prop('checked', false);
        }
        
        // If all of them are checked
        if (selectAll.closest('fieldset').find(':checkbox:checked').not(selectAll).length == checkboxes.length)
        {
            selectAll.prop('checked', true);
        }
    });
    
});
