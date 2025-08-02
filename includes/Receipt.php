<?php
class Receipt {
    private $conn;
    
    public function __construct($db_connection) {
        $this->conn = $db_connection;
    }
    
    public function generateBookingReceipt($booking_id, $send_email = false) {
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    b.id as booking_id,
                    b.start_time,
                    b.end_time,
                    b.status,
                    b.created_at,
                    b.purpose,
                    b.attendees_count,
                    u.full_name as user_name,
                    u.email as user_email,
                    u.phone as user_phone,
                    f.name as facility_name,
                    f.location as facility_location,
                    f.capacity as facility_capacity
                FROM bookings b
                JOIN users u ON b.user_id = u.id
                JOIN facilities f ON b.facility_id = f.id
                WHERE b.id = ?
            ");
            
            $stmt->execute([$booking_id]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$booking) {
                throw new Exception("Booking not found");
            }
            
            // Materials functionality removed
            $materials = [];
            
            $html = '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <title>Booking Receipt #' . $booking['booking_id'] . '</title>
                <style>
                    body {
                        font-family: Arial, sans-serif;
                        line-height: 1.6;
                        margin: 0;
                        padding: 20px;
                    }
                    .receipt {
                        max-width: 800px;
                        margin: 0 auto;
                        border: 1px solid #ccc;
                        padding: 20px;
                    }
                    .header {
                        text-align: center;
                        margin-bottom: 30px;
                    }
                    .logo {
                        max-width: 150px;
                        margin-bottom: 10px;
                    }
                    .receipt-title {
                        font-size: 24px;
                        color: #333;
                        margin-bottom: 5px;
                    }
                    .booking-id {
                        font-size: 16px;
                        color: #666;
                    }
                    .section {
                        margin-bottom: 20px;
                    }
                    .section-title {
                        font-size: 18px;
                        color: #333;
                        margin-bottom: 10px;
                        border-bottom: 2px solid #eee;
                        padding-bottom: 5px;
                    }
                    .info-grid {
                        display: grid;
                        grid-template-columns: 1fr 1fr;
                        gap: 15px;
                    }
                    .info-item {
                        margin-bottom: 10px;
                    }
                    .label {
                        font-weight: bold;
                        color: #666;
                    }
                    .value {
                        color: #333;
                    }
                    .footer {
                        margin-top: 30px;
                        text-align: center;
                        font-size: 14px;
                        color: #666;
                    }
                    @media print {
                        body {
                            padding: 0;
                        }
                        .receipt {
                            border: none;
                        }
                        .no-print {
                            display: none;
                        }
                    }
                </style>
            </head>
            <body>
                <div class="receipt">
                    <div class="header">
                        <h1 class="receipt-title">School Facility Reservation System</h1>
                        <div class="booking-id">Booking Receipt #' . $booking['booking_id'] . '</div>
                    </div>
                    
                    <div class="section">
                        <h2 class="section-title">Booking Details</h2>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="label">Booking Date:</div>
                                <div class="value">' . date('F d, Y', strtotime($booking['start_time'])) . '</div>
                            </div>
                            <div class="info-item">
                                <div class="label">Time:</div>
                                <div class="value">' . date('h:i A', strtotime($booking['start_time'])) . ' - ' . date('h:i A', strtotime($booking['end_time'])) . '</div>
                            </div>
                            <div class="info-item">
                                <div class="label">Status:</div>
                                <div class="value">' . ucfirst($booking['status']) . '</div>
                            </div>
                            <div class="info-item">
                                <div class="label">Booking Created:</div>
                                <div class="value">' . date('F d, Y h:i A', strtotime($booking['created_at'])) . '</div>
                            </div>
                            ' . ($booking['attendees_count'] ? '
                            <div class="info-item">
                                <div class="label">Number of Attendees:</div>
                                <div class="value">' . htmlspecialchars($booking['attendees_count']) . '</div>
                            </div>' : '') . '
                        </div>
                    </div>
                    
                    <div class="section">
                        <h2 class="section-title">Facility Information</h2>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="label">Facility Name:</div>
                                <div class="value">' . htmlspecialchars($booking['facility_name']) . '</div>
                            </div>
                            <div class="info-item">
                                <div class="label">Location:</div>
                                <div class="value">' . htmlspecialchars($booking['facility_location']) . '</div>
                            </div>
                            <div class="info-item">
                                <div class="label">Capacity:</div>
                                <div class="value">' . htmlspecialchars($booking['facility_capacity']) . ' people</div>
                            </div>
                            <div class="info-item">
                                <div class="label">Purpose:</div>
                                <div class="value">' . htmlspecialchars($booking['purpose']) . '</div>
                            </div>
                        </div>
                    </div>
                    
            ' . (!empty($materials) ? '
                    <div class="section">
                        <h2 class="section-title">Materials Needed</h2>
                        <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
                            <thead>
                                <tr style="background-color: #f5f5f5;">
                                    <th style="padding: 8px; text-align: left; border: 1px solid #ddd;">Material</th>
                                    <th style="padding: 8px; text-align: left; border: 1px solid #ddd;">Description</th>
                                    <th style="padding: 8px; text-align: center; border: 1px solid #ddd;">Quantity</th>
                                </tr>
                            </thead>
                            <tbody>' . implode('', array_map(function($material) {
                                return '<tr>
                                    <td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars($material['name']) . '</td>
                                    <td style="padding: 8px; border: 1px solid #ddd;">' . (empty($material['description']) ? 'N/A' : htmlspecialchars($material['description'])) . '</td>
                                    <td style="padding: 8px; text-align: center; border: 1px solid #ddd;">' . htmlspecialchars($material['quantity']) . '</td>
                                </tr>';
                            }, $materials)) . '</tbody>
                        </table>
                    </div>' : '') . '
                    
                    <div class="section">
                        <h2 class="section-title">User Information</h2>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="label">Name:</div>
                                <div class="value">' . htmlspecialchars($booking['user_name']) . '</div>
                            </div>
                            <div class="info-item">
                                <div class="label">Email:</div>
                                <div class="value">' . htmlspecialchars($booking['user_email']) . '</div>
                            </div>
                            <div class="info-item">
                                <div class="label">Phone:</div>
                                <div class="value">' . htmlspecialchars($booking['user_phone']) . '</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="footer">
                        <p>Thank you for using our facility reservation system.</p>
                        <p>For any inquiries, please contact the facility management office.</p>
                    </div>
                    
                    <div class="no-print" style="text-align: center; margin-top: 20px;">
                        <button onclick="window.print()" style="padding: 10px 20px; font-size: 16px; cursor: pointer;">
                            Print Receipt
                        </button>
                    </div>
                </div>
                <script>
                    window.onload = function() {
                        window.print();
                    };
                </script>
            </body>
            </html>';
            
            if ($send_email) {
                require_once 'EmailNotification.php';
                $emailNotification = new EmailNotification();
                $emailSent = $emailNotification->sendBookingReceipt($booking['user_email'], $html);
                
                if (!$emailSent) {
                    throw new Exception("Failed to send receipt email");
                }
            }
            
            return $html;
            
        } catch (Exception $e) {
            throw new Exception("Error generating receipt: " . $e->getMessage());
        }
    }
    

} 