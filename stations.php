<?php
require_once 'config.php';

// Get action from URL parameter
$action = $_GET['action'] ?? '';

error_log("Stations.php called with action: " . $action . ", id: " . ($_GET['id'] ?? 'none'));

switch($action) {
    case 'create':
        createStation($conn);
        break;
    case 'update':
        $stationId = $_GET['id'] ?? 0;
        updateStation($conn, $stationId);
        break;
    case 'delete':
        $stationId = $_GET['id'] ?? 0;
        deleteStation($conn, $stationId);
        break;
    default:
        getStations($conn);
        break;
}

function getStations($conn) {
    try {
        $stmt = $conn->query("SELECT * FROM stations ORDER BY created_at DESC");
        $stations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        respond($stations);
    } catch (Exception $e) {
        respond(null, 'Failed to fetch stations', 500);
    }
}

function createStation($conn) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['owner_id']) || !isset($input['station_name'])) {
            respond(null, 'Missing required fields', 400);
            return;
        }
        
        $stmt = $conn->prepare("INSERT INTO stations (owner_id, station_name, location, price_per_night, total_slots, available_slots, security_features, amenities, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $result = $stmt->execute([
            $input['owner_id'],
            $input['station_name'],
            $input['location'],
            $input['price_per_night'],
            $input['total_slots'],
            $input['available_slots'] ?? $input['total_slots'],
            $input['security_features'] ?? '',
            $input['amenities'] ?? ''
        ]);
        
        if ($result) {
            $stationId = $conn->lastInsertId();
            $stmt = $conn->prepare("SELECT * FROM stations WHERE id = ?");
            $stmt->execute([$stationId]);
            $station = $stmt->fetch(PDO::FETCH_ASSOC);
            respond($station, 'Station created successfully');
        } else {
            respond(null, 'Failed to create station', 500);
        }
    } catch (Exception $e) {
        respond(null, 'Failed to create station: ' . $e->getMessage(), 500);
    }
}

function updateStation($conn, $stationId) {
    try {
        // Get raw input
        $inputJSON = file_get_contents('php://input');
        error_log("updateStation raw input: " . $inputJSON);
        
        $input = json_decode($inputJSON, true);
        error_log("updateStation decoded input: " . print_r($input, true));
        
        if (!$input) {
            respond(null, 'No input data', 400);
            return;
        }
        
        // Check if station exists
        $stmt = $conn->prepare("SELECT * FROM stations WHERE id = ?");
        $stmt->execute([$stationId]);
        $station = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$station) {
            respond(null, 'Station not found', 404);
            return;
        }
        
        // Prepare update query
        $sql = "UPDATE stations SET 
                station_name = ?, 
                location = ?, 
                price_per_night = ?, 
                total_slots = ?, 
                available_slots = ?, 
                security_features = ?, 
                amenities = ? 
                WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        
        // Use existing values if not provided in input
        $station_name = $input['station_name'] ?? $station['station_name'];
        $location = $input['location'] ?? $station['location'];
        $price_per_night = $input['price_per_night'] ?? $station['price_per_night'];
        $total_slots = $input['total_slots'] ?? $station['total_slots'];
        $available_slots = $input['available_slots'] ?? $station['available_slots'];
        $security_features = $input['security_features'] ?? $station['security_features'];
        $amenities = $input['amenities'] ?? $station['amenities'];
        
        error_log("Updating with values: " . print_r([
            $station_name, $location, $price_per_night, $total_slots, 
            $available_slots, $security_features, $amenities, $stationId
        ], true));
        
        $result = $stmt->execute([
            $station_name,
            $location,
            $price_per_night,
            $total_slots,
            $available_slots,
            $security_features,
            $amenities,
            $stationId
        ]);
        
        if ($result) {
            // Fetch updated station
            $stmt = $conn->prepare("SELECT * FROM stations WHERE id = ?");
            $stmt->execute([$stationId]);
            $updatedStation = $stmt->fetch(PDO::FETCH_ASSOC);
            
            error_log("Station updated successfully: " . print_r($updatedStation, true));
            respond($updatedStation, 'Station updated successfully');
        } else {
            error_log("Update failed for station ID: " . $stationId);
            respond(null, 'Update failed', 500);
        }
    } catch (Exception $e) {
        error_log("updateStation error: " . $e->getMessage());
        respond(null, 'Update failed: ' . $e->getMessage(), 500);
    }
}

function deleteStation($conn, $stationId) {
    try {
        // Check if station exists
        $stmt = $conn->prepare("SELECT * FROM stations WHERE id = ?");
        $stmt->execute([$stationId]);
        $station = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$station) {
            respond(null, 'Station not found', 404);
            return;
        }
        
        // Check for active bookings
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM bookings WHERE station_id = ? AND status = 'booked'");
        $stmt->execute([$stationId]);
        $activeBookings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($activeBookings['count'] > 0) {
            respond(null, 'Cannot delete station with active bookings', 400);
            return;
        }
        
        // Delete station
        $stmt = $conn->prepare("DELETE FROM stations WHERE id = ?");
        $result = $stmt->execute([$stationId]);
        
        if ($result) {
            respond(['success' => true, 'id' => $stationId], 'Station deleted successfully');
        } else {
            respond(null, 'Delete failed', 500);
        }
    } catch (Exception $e) {
        respond(null, 'Delete failed: ' . $e->getMessage(), 500);
    }
}
?>