<?php
require_once 'config.php';

// Get action from URL parameter
$action = $_GET['action'] ?? '';

// Enable error reporting for debugging
error_log("Bookings.php called with action: " . $action);

switch($action) {
    case 'create':
        createBooking($conn);
        break;
    case 'cancel':
        $bookingId = $_GET['id'] ?? 0;
        cancelBooking($conn, $bookingId);
        break;
    default:
        getBookings($conn);
        break;
}

function getBookings($conn) {
    try {
        $stmt = $conn->query("SELECT * FROM bookings ORDER BY created_at DESC");
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("getBookings: Found " . count($bookings) . " bookings");
        respond($bookings);
    } catch (Exception $e) {
        error_log("getBookings error: " . $e->getMessage());
        respond(null, 'Failed to fetch bookings', 500);
    }
}

function createBooking($conn) {
    try {
        // Get raw input
        $inputJSON = file_get_contents('php://input');
        error_log("createBooking raw input: " . $inputJSON);
        
        $input = json_decode($inputJSON, true);
        error_log("createBooking decoded input: " . print_r($input, true));
        
        if (!$input || !isset($input['user_id']) || !isset($input['station_id'])) {
            respond(null, 'Missing required fields', 400);
            return;
        }
        
        // Check if station exists and has available slots
        $stmt = $conn->prepare("SELECT * FROM stations WHERE id = ?");
        $stmt->execute([$input['station_id']]);
        $station = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$station) {
            respond(null, 'Station not found', 404);
            return;
        }
        
        if ($station['available_slots'] <= 0) {
            respond(null, 'No available slots', 400);
            return;
        }
        
        // Start transaction
        $conn->beginTransaction();
        
        // Create booking
        $stmt = $conn->prepare("INSERT INTO bookings (user_id, station_id, booking_date, status, created_at) VALUES (?, ?, ?, 'booked', NOW())");
        $bookingResult = $stmt->execute([
            $input['user_id'],
            $input['station_id'],
            $input['booking_date'] ?? date('Y-m-d')
        ]);
        
        if (!$bookingResult) {
            $conn->rollBack();
            respond(null, 'Failed to create booking', 500);
            return;
        }
        
        $bookingId = $conn->lastInsertId();
        error_log("Booking created with ID: " . $bookingId);
        
        // Update station available slots
        $newAvailableSlots = $station['available_slots'] - 1;
        $stmt = $conn->prepare("UPDATE stations SET available_slots = ? WHERE id = ?");
        $updateResult = $stmt->execute([$newAvailableSlots, $input['station_id']]);
        
        if (!$updateResult) {
            $conn->rollBack();
            respond(null, 'Failed to update station slots', 500);
            return;
        }
        
        error_log("Station updated: new available slots = " . $newAvailableSlots);
        
        // Commit transaction
        $conn->commit();
        
        // Fetch the newly created booking with station details
        $stmt = $conn->prepare("
            SELECT b.*, s.station_name, s.location, s.price_per_night 
            FROM bookings b 
            LEFT JOIN stations s ON b.station_id = s.id 
            WHERE b.id = ?
        ");
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        respond($booking, 'Booking created successfully');
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log("createBooking error: " . $e->getMessage());
        respond(null, 'Booking failed: ' . $e->getMessage(), 500);
    }
}

function cancelBooking($conn, $bookingId) {
    try {
        error_log("cancelBooking called for ID: " . $bookingId);
        
        // Start transaction
        $conn->beginTransaction();
        
        // Get booking details
        $stmt = $conn->prepare("SELECT * FROM bookings WHERE id = ?");
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$booking) {
            $conn->rollBack();
            error_log("Booking not found: " . $bookingId);
            respond(null, 'Booking not found', 404);
            return;
        }
        
        error_log("Booking found: " . print_r($booking, true));
        
        // Check if already cancelled
        if ($booking['status'] === 'cancelled') {
            $conn->rollBack();
            respond(null, 'Booking already cancelled', 400);
            return;
        }
        
        // Update booking status
        $stmt = $conn->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?");
        $result = $stmt->execute([$bookingId]);
        
        if (!$result) {
            $conn->rollBack();
            error_log("Failed to update booking status");
            respond(null, 'Failed to cancel booking', 500);
            return;
        }
        
        error_log("Booking status updated to cancelled");
        
        // Increase available slots
        $stmt = $conn->prepare("UPDATE stations SET available_slots = available_slots + 1 WHERE id = ?");
        $stmt->execute([$booking['station_id']]);
        
        error_log("Station slots increased for station ID: " . $booking['station_id']);
        
        $conn->commit();
        
        // Fetch updated booking
        $stmt = $conn->prepare("SELECT * FROM bookings WHERE id = ?");
        $stmt->execute([$bookingId]);
        $updatedBooking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        respond($updatedBooking, 'Booking cancelled successfully');
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log("cancelBooking error: " . $e->getMessage());
        respond(null, 'Cancel failed: ' . $e->getMessage(), 500);
    }
}
?>
