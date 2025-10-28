<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminEventsController extends Controller
{
    public function stream()
    {
        // SSE "hello world" con keep-alive
        $response = new StreamedResponse(function () {
            while (ob_get_level() > 0) { ob_end_flush(); }
            header('Cache-Control: no-cache');
            header('X-Accel-Buffering: no'); // nginx

            // evento inicial opcional
            echo "event: ping\n";
            echo 'data: {"ok":true}' . "\n\n";
            @ob_flush(); flush();

            // Mantener abierto; envía ping cada 20s
            while (true) {
                echo "event: ping\n";
                echo 'data: {"ts":' . time() . "}\n\n";
                @ob_flush(); flush();
                sleep(20);
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Connection', 'keep-alive');

        return $response;
    }
}
