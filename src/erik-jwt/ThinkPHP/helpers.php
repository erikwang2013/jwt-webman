<?php

if (!function_exists('jwt')) {
    function jwt(): \ErikJwt\JWT
    {
        return app('erik.jwt');
    }
}
