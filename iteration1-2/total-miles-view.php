<?php
require_once( dirname( __FILE__, 5 ) . '/wp-config.php' );

global $wpdb;
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Fetch total miles per TSR + agent name
$totals = $wpdb->get_results("
    SELECT m.tsr, ac.agent_name, SUM(m.points) as total_miles
    FROM wpk4_backend_gaura_points m
    LEFT JOIN wpk4_backend_agent_codes ac ON m.tsr = ac.tsr
    GROUP BY m.tsr
    ORDER BY total_miles DESC
");

// Fetch all transaction records grouped by TSR
$all_data = $wpdb->get_results("
    SELECT m.*, ac.agent_name
    FROM wpk4_backend_gaura_points m
    LEFT JOIN wpk4_backend_agent_codes ac ON m.tsr = ac.tsr
    ORDER BY m.tsr, m.transaction_date DESC
");
$data_by_tsr = [];
foreach ($all_data as $row) {
    $data_by_tsr[$row->tsr][] = $row;
}
?>

<style>
    body {
        font-family: 'Segoe UI', sans-serif;
        margin: 2rem;
        background-color: #f9fafb;
        color: #333;
    }

    h1 {
        font-size: 4rem;
        margin-bottom: 1.5rem;
        color: #0073aa;
    }

    .summary-table {
        width: 100%;
        border-collapse: collapse;
        background: #fff;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }

    .summary-table thead {
        background-color: #f1f5f9;
    }

    .summary-table th, .summary-table td {
        padding: 1rem;
        text-align: left;
    }

    .summary-table th {
        font-weight: 600;
        color: #333;
    }

    .summary-table tbody tr:nth-child(even) {
        background-color: #f9fafc;
    }

    .summary-table tbody tr:hover {
        background-color: #e6f4ff;
    }

    .summary-table td {
        border-bottom: 1px solid #e5e7eb;
    }

    @media (max-width: 600px) {
        .summary-table thead {
            display: none;
        }

        .summary-table, .summary-table tbody, .summary-table tr, .summary-table td {
            display: block;
            width: 100%;
        }

        .summary-table tr {
            margin-bottom: 1rem;
            border-bottom: 2px solid #e2e8f0;
        }

        .summary-table td {
            padding: 0.75rem 1rem;
            text-align: right;
            position: relative;
        }

        .summary-table td::before {
            content: attr(data-label);
            position: absolute;
            left: 1rem;
            top: 0.75rem;
            font-weight: bold;
            color: #555;
            text-transform: uppercase;
        }
    }
</style>



<script>
document.addEventListener("DOMContentLoaded", function() {
    document.querySelectorAll(".accordion-header").forEach(header => {
        header.addEventListener("click", () => {
            header.parentElement.classList.toggle("active");
        });
    });
});
</script>

<div class="wrap">
    <h1>Total Miles per Agent</h1>
    <table class="summary-table">
        <thead>
            <tr>
                <th>Agent Name</th>
                <th>Total Miles</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($totals as $row): ?>
                <tr>
                    <td data-label="Agent Name"><?php echo esc_html($row->agent_name ?: 'N/A'); ?></td>
                    <td data-label="Total Miles"><?php echo number_format($row->total_miles); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>



