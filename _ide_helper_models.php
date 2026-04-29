<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * @property int $id
 * @property int $property_id
 * @property \Illuminate\Support\Carbon $start_date
 * @property \Illuminate\Support\Carbon $end_date
 * @property string $reason
 * @property string|null $notes
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $createdBy
 * @property-read \App\Models\Property $property
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AvailabilityBlock newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AvailabilityBlock newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AvailabilityBlock query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AvailabilityBlock whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AvailabilityBlock whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AvailabilityBlock whereEndDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AvailabilityBlock whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AvailabilityBlock whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AvailabilityBlock wherePropertyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AvailabilityBlock whereReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AvailabilityBlock whereStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AvailabilityBlock whereUpdatedAt($value)
 */
	class AvailabilityBlock extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $booking_ref
 * @property int $user_id
 * @property int $property_id
 * @property \Illuminate\Support\Carbon $check_in_date
 * @property \Illuminate\Support\Carbon $check_out_date
 * @property int $num_nights
 * @property int $num_guests
 * @property numeric $base_amount
 * @property numeric $extras_amount
 * @property numeric $discount_amount
 * @property numeric $total_amount
 * @property numeric $amount_paid
 * @property numeric $balance_due
 * @property string $status
 * @property string $payment_status
 * @property string $source
 * @property string|null $paymongo_session_id
 * @property string|null $paymongo_payment_type
 * @property string|null $special_requests
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property string|null $cancellation_reason
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BookingExtra> $extras
 * @property-read int|null $extras_count
 * @property-read string $payment_status_badge
 * @property-read string $status_badge
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\HousekeepingTask> $housekeepingTasks
 * @property-read int|null $housekeeping_tasks_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Payment> $payments
 * @property-read int|null $payments_count
 * @property-read \App\Models\Property $property
 * @property-read \App\Models\Review|null $review
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereAmountPaid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereBalanceDue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereBaseAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereBookingRef($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereCancellationReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereCancelledAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereCheckInDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereCheckOutDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereDiscountAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereExtrasAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereNumGuests($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereNumNights($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking wherePaymentStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking wherePaymongoPaymentType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking wherePaymongoSessionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking wherePropertyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereSpecialRequests($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereTotalAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereUserId($value)
 */
	class Booking extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $booking_id
 * @property string $item_name
 * @property string|null $description
 * @property int $quantity
 * @property numeric $unit_price
 * @property numeric $total
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Booking $booking
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingExtra newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingExtra newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingExtra query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingExtra whereBookingId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingExtra whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingExtra whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingExtra whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingExtra whereItemName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingExtra whereQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingExtra whereTotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingExtra whereUnitPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingExtra whereUpdatedAt($value)
 */
	class BookingExtra extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $code
 * @property string|null $label
 * @property string $type
 * @property numeric $value
 * @property int $min_nights
 * @property int|null $usage_limit null = unlimited
 * @property int $used_count
 * @property \Illuminate\Support\Carbon|null $expiry_date
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Discount newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Discount newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Discount query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Discount whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Discount whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Discount whereExpiryDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Discount whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Discount whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Discount whereLabel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Discount whereMinNights($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Discount whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Discount whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Discount whereUsageLimit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Discount whereUsedCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Discount whereValue($value)
 */
	class Discount extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $property_id
 * @property int|null $assigned_to
 * @property int|null $booking_id
 * @property string $task_type
 * @property string|null $scheduled_date
 * @property \Illuminate\Support\Carbon $due_date
 * @property string $status
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $assignedTo
 * @property-read \App\Models\Booking|null $booking
 * @property-read \App\Models\Property $property
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HousekeepingTask newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HousekeepingTask newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HousekeepingTask query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HousekeepingTask whereAssignedTo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HousekeepingTask whereBookingId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HousekeepingTask whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HousekeepingTask whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HousekeepingTask whereDueDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HousekeepingTask whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HousekeepingTask whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HousekeepingTask wherePropertyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HousekeepingTask whereScheduledDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HousekeepingTask whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HousekeepingTask whereTaskType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HousekeepingTask whereUpdatedAt($value)
 */
	class HousekeepingTask extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property string|null $type
 * @property string $title
 * @property string $message
 * @property bool $is_read
 * @property \Illuminate\Support\Carbon|null $sent_at
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereIsRead($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereSentAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereUserId($value)
 */
	class Notification extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property numeric $price
 * @property array<array-key, mixed>|null $inclusions JSON array of what is included
 * @property int $validity_days
 * @property int $max_guests
 * @property string|null $image_path
 * @property bool $is_active
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read string $image_url
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereImagePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereInclusions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereMaxGuests($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereValidityDays($value)
 */
	class Package extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $booking_id
 * @property numeric $amount
 * @property string $payment_method
 * @property string $payment_type
 * @property string|null $transaction_ref
 * @property array<array-key, mixed>|null $gateway_response
 * @property string $status
 * @property int|null $processed_by
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon $payment_date
 * @property string|null $reference_number
 * @property int|null $received_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Booking $booking
 * @property-read string $method_label
 * @property-read \App\Models\User|null $processedBy
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereBookingId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereGatewayResponse($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment wherePaymentDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment wherePaymentMethod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment wherePaymentType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereProcessedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereReceivedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereReferenceNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereTransactionRef($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereUpdatedAt($value)
 */
	class Payment extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $property_id
 * @property string $label e.g. Christmas Rate, Summer Rate
 * @property \Illuminate\Support\Carbon $start_date
 * @property \Illuminate\Support\Carbon $end_date
 * @property numeric $price
 * @property string $type
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Property $property
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PricingRule newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PricingRule newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PricingRule query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PricingRule whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PricingRule whereEndDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PricingRule whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PricingRule whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PricingRule whereLabel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PricingRule wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PricingRule wherePropertyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PricingRule whereStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PricingRule whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PricingRule whereUpdatedAt($value)
 */
	class PricingRule extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $property_name
 * @property string $type
 * @property string|null $description
 * @property int $max_capacity
 * @property numeric $base_price
 * @property numeric|null $weekend_price
 * @property array<array-key, mixed>|null $amenities
 * @property numeric|null $floor_area_sqm
 * @property int|null $floor_level
 * @property string $status
 * @property bool $is_featured
 * @property int $sort_order
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AvailabilityBlock> $availabilityBlocks
 * @property-read int|null $availability_blocks_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Booking> $bookings
 * @property-read int|null $bookings_count
 * @property-read \App\Models\User|null $createdBy
 * @property-read \App\Models\Booking|null $currentBooking
 * @property-read float $average_rating
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\HousekeepingTask> $housekeepingTasks
 * @property-read int|null $housekeeping_tasks_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PropertyImage> $images
 * @property-read int|null $images_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PricingRule> $pricingRules
 * @property-read int|null $pricing_rules_count
 * @property-read \App\Models\PropertyImage|null $primaryImage
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Review> $reviews
 * @property-read int|null $reviews_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Property newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Property newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Property query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Property whereAmenities($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Property whereBasePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Property whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Property whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Property whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Property whereFloorAreaSqm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Property whereFloorLevel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Property whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Property whereIsFeatured($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Property whereMaxCapacity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Property wherePropertyName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Property whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Property whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Property whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Property whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Property whereWeekendPrice($value)
 */
	class Property extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $property_id
 * @property string $image_path
 * @property string|null $alt_text
 * @property bool $is_primary
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read string $url
 * @property-read \App\Models\Property $property
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PropertyImage newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PropertyImage newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PropertyImage query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PropertyImage whereAltText($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PropertyImage whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PropertyImage whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PropertyImage whereImagePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PropertyImage whereIsPrimary($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PropertyImage wherePropertyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PropertyImage whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PropertyImage whereUpdatedAt($value)
 */
	class PropertyImage extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $booking_id
 * @property int $user_id
 * @property int $property_id
 * @property int $overall_rating
 * @property int|null $cleanliness
 * @property int|null $service
 * @property int|null $value
 * @property string|null $comment
 * @property string|null $admin_reply
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Booking $booking
 * @property-read string $stars_html
 * @property-read \App\Models\Property $property
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review whereAdminReply($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review whereBookingId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review whereCleanliness($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review whereComment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review whereOverallRating($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review wherePropertyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review whereService($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review whereValue($value)
 */
	class Review extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $setting_key
 * @property string|null $setting_value
 * @property string $data_type
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereDataType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereSettingKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereSettingValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereUpdatedAt($value)
 */
	class Setting extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property string $action e.g. created_booking, updated_property, checked_in_guest
 * @property string|null $target_table
 * @property int|null $target_id
 * @property string|null $description
 * @property array<array-key, mixed>|null $old_values Data before the change
 * @property array<array-key, mixed>|null $new_values Data after the change
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffLog whereAction($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffLog whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffLog whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffLog whereNewValues($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffLog whereOldValues($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffLog whereTargetId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffLog whereTargetTable($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffLog whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffLog whereUserAgent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffLog whereUserId($value)
 */
	class StaffLog extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $full_name
 * @property string $email
 * @property string $password
 * @property string|null $phone
 * @property string $role
 * @property string|null $profile_image
 * @property string|null $address
 * @property string|null $id_type
 * @property string|null $id_number
 * @property int $status 1=Active, 0=Deactivated
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $last_login
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Booking> $bookings
 * @property-read int|null $bookings_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\HousekeepingTask> $housekeepingTasks
 * @property-read int|null $housekeeping_tasks_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Notification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Review> $reviews
 * @property-read int|null $reviews_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\StaffLog> $staffLogs
 * @property-read int|null $staff_logs_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereFullName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereIdNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereIdType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLastLogin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereProfileImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 */
	class User extends \Eloquent {}
}

