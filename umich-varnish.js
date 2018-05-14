var umVarnishTimer     = false;
var umVarnishStatusImg = false;
function umVarnishPurge( type ) {
    var umvData = {
        nonce : umvNonce,
        action: 'umvarnish_clear',
        type  : type,
        url   : window.location.href
    };

    if( umVarnishTimer ) {
        clearTimeout( umVarnishTimer );
    }

    if( umVarnishStatusImg === false ) {
        umVarnishStatusImg = jQuery('#wp-admin-bar-umich-varnish-root > *:first-child > img');
    }

    // show working status icon
    umVarnishStatusImg.attr( 'src',
        umVarnishStatusImg.attr('src')
            .replace(/(error|success)\.svg/, 'working.svg')
    ).css({
        visibility: 'visible',
        display   : '',
        opacity   : ''
    });

    // Send purge request
    jQuery.post( ajaxurl.replace( /^https?:/, window.location.protocol ), umvData, function( response ){
        if( response.nonce.length ) {
            umvNonce = response.nonce;
        }

        var resStatus = 'error';

        // invalid request
        if( typeof response.status == 'string' ) {
            resStatus = 'error';
        }
        else {
            // check for success from varnish response
            if( jQuery( response.status.res.body ).filter('title').text().indexOf('200 Purged') >= 0 ) {
                resStatus = 'success';
            }
        }

        // change status icon
        umVarnishStatusImg.css({
            display   : 'none',
            visibility: 'hidden'
        }).attr( 'src',
            umVarnishStatusImg.attr('src').replace('working.svg', resStatus +'.svg')
        ).css('visibility','visible').fadeIn();

        // set timer to clear status icon
        umVarnishTimer = setTimeout(function(){
            umVarnishStatusImg.fadeOut(function(){
                umVarnishStatusImg.css({
                    visibility: 'hidden',
                    display   : '',
                    opacity   : ''
                }).attr( 'src',
                    umVarnishStatusImg.attr('src')
                        .replace(/(error|success)\.svg/, 'working.svg')
                );
            });
        }, 5000 );

    }, 'json' );

    return false;
}
