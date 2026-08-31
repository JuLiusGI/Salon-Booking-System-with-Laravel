<?php

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Services\Qr\AppointmentQrService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves an appointment's QR code as an image.
 *
 * Kept on its own endpoint rather than inlined into the page so the raw token
 * never appears in an Inertia prop or in the page source. It is necessarily
 * inside the rendered image, which is the point of a QR code, but it is not
 * lying around in the JSON payload for anything else to pick up.
 */
class AppointmentQrController extends Controller
{
    public function __construct(private readonly AppointmentQrService $qr) {}

    public function __invoke(Request $request, Appointment $appointment): Response
    {
        // Same policy as viewing the appointment: the customer it belongs to,
        // or salon staff entitled to see it.
        $this->authorize('view', $appointment);

        return response($this->qr->svgFor($appointment), 200, [
            'Content-Type' => 'image/svg+xml',

            // Private, because the image contains the token. A shared cache
            // must never hand one customer's code to another.
            'Cache-Control' => 'private, max-age=300, no-transform',
        ]);
    }
}
