var mcVarnishTimer     = false;
var mcVarnishStatusImg = false;
function mcVarnishPurge( type ) {
    var mcvData = {
        nonce : mcvNonce,
        action: 'mcvarnish_clear',
        type  : type,
        url   : window.location.href
    };

    if( mcVarnishTimer ) {
        clearTimeout( mcVarnishTimer );
    }

    if( mcVarnishStatusImg === false ) {
        mcVarnishStatusImg = jQuery('#wp-admin-bar-mc-varnish-root > *:first-child > img');
    }

    // show working status icon
    mcVarnishStatusImg.attr( 'src',
        mcVarnishStatusImg.attr('src')
            .replace(/(error|success)\.svg/, 'working.svg')
    ).css({
        visibility: 'visible',
        display   : '',
        opacity   : ''
    });

    // Send purge request
    jQuery.post( ajaxurl.replace( /^https?:/, window.location.protocol ), mcvData, function( response ){
        if( response.nonce.length ) {
            mcvNonce = response.nonce;
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
        mcVarnishStatusImg.css({
            display   : 'none',
            visibility: 'hidden'
        }).attr( 'src',
            mcVarnishStatusImg.attr('src').replace('working.svg', resStatus +'.svg')
        ).css('visibility','visible').fadeIn();

        // set timer to clear status icon
        mcVarnishTimer = setTimeout(function(){
            mcVarnishStatusImg.fadeOut(function(){
                mcVarnishStatusImg.css({
                    visibility: 'hidden',
                    display   : '',
                    opacity   : ''
                }).attr( 'src',
                    mcVarnishStatusImg.attr('src')
                        .replace(/(error|success)\.svg/, 'working.svg')
                );
            });
        }, 5000 );

    }, 'json' );

    return false;
}
