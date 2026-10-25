<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The browser engine could not draw a CV, and the fallback engine cannot
 * draw its script either, so there is no PDF to give for now.
 */
class CvPdfUnavailable extends RuntimeException {}
