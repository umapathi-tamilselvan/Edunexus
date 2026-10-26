<?php

namespace App\Http\Middleware;

use App\Support\AcademicYearContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Puts the request in the academic year named by the X-Academic-Year
 * header (a year id). Without the header it works in the current year.
 */
class SelectedAcademicYear
{
    public function __construct(private AcademicYearContext $academicYear) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $yearId = (string) $request->header(AcademicYearContext::HEADER);

        if ($yearId !== '') {
            // Anything but an id selects no year, which fails when used.
            $this->academicYear->select(ctype_digit($yearId) ? (int) $yearId : 0);
        }

        return $next($request);
    }
}
