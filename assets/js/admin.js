jQuery(function($) {
    $('#big-sitemap-generate').on('click', function() {
        const $btn = $(this);
        const orig = $btn.text();
        $btn.text('Generating...').prop('disabled', true);
        $.post(bigSitemapAjax.url, {
            action: 'big_sitemap_generate_now',
            nonce: bigSitemapAjax.nonce
        }, function(res) {
            if (res.success) {
                $('#big-sitemap-message').removeClass('notice-error').addClass('notice-success').html('<p><strong>Success: ' + res.data.message + '</strong></p>').show();
                setTimeout(() => location.reload(), 1500);
            } else {
                $('#big-sitemap-message').removeClass('notice-success').addClass('notice-error').html('<p><strong>Error: ' + res.data + '</strong></p>').show();
            }
            $btn.text(orig).prop('disabled', false);
        });
    });

    $('#save-url-overrides').on('click', function() {
        const overrides = {};
        $('#big-sitemap-url-table tbody tr').each(function() {
            const $row = $(this);
            const loc = $row.data('loc');
            const priority = $row.find('.priority-select').val();
            const changefreq = $row.find('.changefreq-select').val();
            const exclude = $row.find('.exclude-check').is(':checked');
            overrides[loc] = { priority, changefreq, exclude };
        });
        const $btn = $(this);
        const orig = $btn.text();
        $btn.text('Saving...').prop('disabled', true);
        $.post(bigSitemapAjax.url, {
            action: 'big_sitemap_save_overrides',
            nonce: bigSitemapAjax.nonce,
            overrides: JSON.stringify(overrides)
        }, function(res) {
            if (res.success) {
                alert('Success: ' + res.data.message);
                location.reload();
            } else {
                alert('Error: ' + res.data);
            }
            $btn.text(orig).prop('disabled', false);
        });
    });

    $('#save-xml-raw').on('click', function() {
        const xml = $('#big-sitemap-xml-editor').val();
        const $btn = $(this);
        const orig = $btn.text();
        $btn.text('Saving...').prop('disabled', true);
        $.post(bigSitemapAjax.url, {
            action: 'big_sitemap_save_xml',
            nonce: bigSitemapAjax.nonce,
            xml: xml
        }, function(res) {
            if (res.success) alert('Success: ' + res.data.message);
            else alert('Error: ' + res.data);
            $btn.text(orig).prop('disabled', false);
        });
    });
});
