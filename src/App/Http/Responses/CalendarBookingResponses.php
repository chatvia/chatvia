<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Carbon\CarbonImmutable;
use Domain\Booking\Data\BookMeetingResultData;
use Domain\Booking\Enums\BookingFailure;
use Domain\Booking\Exceptions\BookingException;
use Domain\Form\Enums\FormClaimFailure;
use Domain\Form\Exceptions\FormNotClaimableException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

/**
 * The JSON the chat's and widget's booking calendar reads, shared by both
 * surfaces' controllers so the block handles one contract: the status says
 * what happened, the message is shown to the visitor.
 */
final class CalendarBookingResponses
{
    /**
     * @param  Collection<int, CarbonImmutable>  $slots
     */
    public static function slots(Collection $slots): JsonResponse
    {
        return response()->json([
            'slots' => $slots->map(static fn (CarbonImmutable $slot): array => ['start_time' => $slot->toIso8601ZuluString()])->values()->all(),
        ]);
    }

    public static function booked(BookMeetingResultData $result): JsonResponse
    {
        return response()->json([
            'status' => $result->status->value,
            'start_time' => $result->startTime->toIso8601ZuluString(),
            'message' => __($result->message),
        ]);
    }

    public static function failure(FormNotClaimableException|BookingException $e): JsonResponse
    {
        if ($e instanceof FormNotClaimableException) {
            return $e->reason === FormClaimFailure::Expired
                ? response()->json(['status' => 'expired', 'message' => __('This calendar has expired. Ask for a new one to book a meeting.')], JsonResponse::HTTP_GONE)
                : response()->json(['status' => 'submitted', 'message' => __('This meeting is already booked.')], JsonResponse::HTTP_CONFLICT);
        }

        return match ($e->reason) {
            BookingFailure::SlotTaken => response()->json(['status' => 'slot_taken', 'message' => __($e->getMessage())], JsonResponse::HTTP_CONFLICT),
            BookingFailure::BookingNotAllowed => response()->json([
                'status' => 'booking_not_allowed',
                'message' => __($e->getMessage()),
                'fallback_url' => $e->fallbackUrl,
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY),
            BookingFailure::NotConnected, BookingFailure::Unavailable => response()->json(
                ['status' => 'unavailable', 'message' => __(BookingFailure::Unavailable->message())],
                JsonResponse::HTTP_SERVICE_UNAVAILABLE,
            ),
        };
    }
}
