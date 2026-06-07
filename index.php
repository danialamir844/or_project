<?php
require_once 'config.php';

$result = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get values from form
    $profit_table = $_POST['profit_table'];
    $profit_chair = $_POST['profit_chair'];
    $hours_table = $_POST['hours_table'];
    $hours_chair = $_POST['hours_chair'];
    $total_hours = $_POST['total_hours'];
    $wood_table = $_POST['wood_table'];
    $wood_chair = $_POST['wood_chair'];
    $total_wood = $_POST['total_wood'];
    $constraint1_sign = $_POST['constraint1_sign'];
    $constraint2_sign = $_POST['constraint2_sign'];
    
    // Calculate corner points
    $points = [];
    
    // Point 1: (0, 0)
    $points[] = ['x1' => 0, 'x2' => 0];
    
    // Point 2: Intersection with X-axis for constraint 1
    if ($hours_table != 0) {
        $x = $total_hours / $hours_table;
        if ($x >= 0) $points[] = ['x1' => $x, 'x2' => 0];
    }
    
    // Point 3: Intersection with X-axis for constraint 2
    if ($wood_table != 0) {
        $x = $total_wood / $wood_table;
        if ($x >= 0) $points[] = ['x1' => $x, 'x2' => 0];
    }
    
    // Point 4: Intersection with Y-axis for constraint 1
    if ($hours_chair != 0) {
        $y = $total_hours / $hours_chair;
        if ($y >= 0) $points[] = ['x1' => 0, 'x2' => $y];
    }
    
    // Point 5: Intersection with Y-axis for constraint 2
    if ($wood_chair != 0) {
        $y = $total_wood / $wood_chair;
        if ($y >= 0) $points[] = ['x1' => 0, 'x2' => $y];
    }
    
    // Point 6: Intersection of both constraints
    $det = ($hours_table * $wood_chair) - ($wood_table * $hours_chair);
    if ($det != 0) {
        $x = (($total_hours * $wood_chair) - ($total_wood * $hours_chair)) / $det;
        $y = (($hours_table * $total_wood) - ($wood_table * $total_hours)) / $det;
        if ($x >= 0 && $y >= 0) {
            $points[] = ['x1' => $x, 'x2' => $y];
        }
    }
    
    // Calculate profit for each point and check feasibility with constraint signs
    $feasible_points = [];
    foreach ($points as $p) {
        $x1 = $p['x1'];
        $x2 = $p['x2'];
        
        // Apply constraint signs
        $constraint1_ok = false;
        $constraint2_ok = false;
        
        // Constraint 1 check
        $c1_value = $hours_table * $x1 + $hours_chair * $x2;
        if ($constraint1_sign == '<=') $constraint1_ok = $c1_value <= $total_hours + 0.001;
        elseif ($constraint1_sign == '>=') $constraint1_ok = $c1_value >= $total_hours - 0.001;
        elseif ($constraint1_sign == '<') $constraint1_ok = $c1_value < $total_hours + 0.001;
        elseif ($constraint1_sign == '>') $constraint1_ok = $c1_value > $total_hours - 0.001;
        elseif ($constraint1_sign == '=') $constraint1_ok = abs($c1_value - $total_hours) <= 0.001;
        
        // Constraint 2 check
        $c2_value = $wood_table * $x1 + $wood_chair * $x2;
        if ($constraint2_sign == '<=') $constraint2_ok = $c2_value <= $total_wood + 0.001;
        elseif ($constraint2_sign == '>=') $constraint2_ok = $c2_value >= $total_wood - 0.001;
        elseif ($constraint2_sign == '<') $constraint2_ok = $c2_value < $total_wood + 0.001;
        elseif ($constraint2_sign == '>') $constraint2_ok = $c2_value > $total_wood - 0.001;
        elseif ($constraint2_sign == '=') $constraint2_ok = abs($c2_value - $total_wood) <= 0.001;
        
        // Check non-negativity and constraints
        if ($x1 >= 0 && $x2 >= 0 && $constraint1_ok && $constraint2_ok) {
            $profit = ($profit_table * $x1) + ($profit_chair * $x2);
            $feasible_points[] = [
                'x1' => round($x1, 2),
                'x2' => round($x2, 2),
                'profit' => round($profit, 2)
            ];
        }
    }
    
    // Remove duplicate points
    $unique_points = [];
    foreach ($feasible_points as $p) {
        $key = $p['x1'] . ',' . $p['x2'];
        if (!isset($unique_points[$key])) {
            $unique_points[$key] = $p;
        }
    }
    $feasible_points = array_values($unique_points);
    
    // Find optimal solution (max profit)
    $optimal = null;
    $max_profit = -999999;
    foreach ($feasible_points as $point) {
        if ($point['profit'] > $max_profit) {
            $max_profit = $point['profit'];
            $optimal = $point;
        }
    }
    
    $result = [
        'optimal' => $optimal,
        'all_points' => $feasible_points,
        'constraint1_sign' => $constraint1_sign,
        'constraint2_sign' => $constraint2_sign,
        'hours_table' => $hours_table,
        'hours_chair' => $hours_chair,
        'total_hours' => $total_hours,
        'wood_table' => $wood_table,
        'wood_chair' => $wood_chair,
        'total_wood' => $total_wood,
        'profit_table' => $profit_table,
        'profit_chair' => $profit_chair
    ];
    
    // Save to database
    if ($optimal) {
        $stmt = $conn->prepare("INSERT INTO solutions (tables_produced, chairs_produced, profit) VALUES (?, ?, ?)");
        $stmt->bind_param("iid", $optimal['x1'], $optimal['x2'], $optimal['profit']);
        $stmt->execute();
        $stmt->close();
    }
}

// Get history
$history = $conn->query("SELECT * FROM solutions ORDER BY date DESC LIMIT 10");
?>

<html>
<head>
    <title>LP Furniture Solver</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f0f2f5;
        }
        .container {
            max-width: 1200px;
            margin: auto;
        }
        h1 {
            text-align: center;
            color: #333;
        }
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .card h2 {
            margin-top: 0;
            color: #4CAF50;
            border-bottom: 2px solid #4CAF50;
            padding-bottom: 10px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
        }
        input, select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-sizing: border-box;
        }
        button {
            background: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
        }
        button:hover {
            background: #45a049;
        }
        .result-box {
            background: #e8f5e9;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .result-value {
            font-size: 20px;
            font-weight: bold;
            margin: 10px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #4CAF50;
            color: white;
        }
        .optimal {
            background: #c8e6c9;
            font-weight: bold;
        }
        .constraint-row {
            display: grid;
            grid-template-columns: 1fr 0.5fr 1fr;
            gap: 10px;
            align-items: center;
        }
        @media (max-width: 768px) {
            .grid {
                grid-template-columns: 1fr;
            }
            .constraint-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🪑 Furniture Production Optimizer 🛋️</h1>
        <p style="text-align: center;">Linear Programming Solver - Maximize Profit</p>
        
        <div class="grid">
            <!-- Input Form -->
            <div class="card">
                <h2>Enter Problem Data</h2>
                <form method="POST">
                    <h3>Profit per Unit</h3>
                    <div class="form-group">
                        <label>Tables Profit (MYR)</label>
                        <input type="number" name="profit_table" step="any" placeholder=" x " required>
                    </div>
                    <div class="form-group">
                        <label>Chairs Profit (MYR)</label>
                        <input type="number" name="profit_chair" step="any" placeholder=" y " required>
                    </div>
                    
                    <h3>Constraint 1: Labor Hours</h3>
                    <div class="constraint-row">
                         <div class="form-group">
                        <label>Tables Labor Hours (Hours)</label>
                        <input type="number" name="hours_table" step="any" placeholder=" x " required>
                    </div>
						  <div class="form-group">
                        <label>Chairs Labor Hours (Hours)</label>
                        <input type="number" name="hours_chair" step="any" placeholder=" y " required>
                    </div>
					
                        <select name="constraint1_sign">
                            <option value="<=" selected>≤</option>
                            <option value=">=">≥</option>
                            <option value="<">&lt;</option>
                            <option value=">">&gt;</option>
                            <option value="=">=</option>
                        </select>
                        <input type="number" name="total_hours" step="any" placeholder="Total Hours" required>
                    </div>
                    
                    <h3>Constraint 2: Wood Material</h3>
                    <div class="constraint-row">
                         <div class="form-group">
                        <label>Tables Wood Material</label>
                        <input type="number" name="wood_table" step="any" placeholder=" x " required>
                    </div>
						 <div class="form-group">
                        <label>Chairs Labor Hours</label>
                        <input type="number" name="wood_chair" step="any" placeholder=" y " required>
                    </div>
					
                        <select name="constraint2_sign">
                            <option value="<=" selected>≤</option>
                            <option value=">=">≥</option>
                            <option value="<">&lt;</option>
                            <option value=">">&gt;</option>
                            <option value="=">=</option>
                        </select>
                        <input type="number" name="total_wood" step="any" placeholder="Total Wood" required>
                    </div>
                    
                    <button type="submit">Solve Problem</button>
                </form>
            </div>
            
            <!-- Results -->
            <div>
                <?php if ($result && $result['optimal']): ?>
                <div class="result-box">
                    <h3>✅ Optimal Solution</h3>
                    <div class="result-value">Tables to produce: <?php echo $result['optimal']['x1']; ?> units</div>
                    <div class="result-value">Chairs to produce: <?php echo $result['optimal']['x2']; ?> units</div>
                    <div class="result-value">Maximum Profit: $<?php echo number_format($result['optimal']['profit'], 2); ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Corner Points Table -->
        <?php if ($result && count($result['all_points']) > 0): ?>
        <div class="card" style="margin-top: 20px;">
            <h2>All Feasible Corner Points (x=0, y=0 and intercepts)</h2>
            <table>
                <thead>
                    <tr>
                        <th>Tables (x)</th>
                        <th>Chairs (y)</th>
                        <th>Profit (MYR)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    usort($result['all_points'], function($a, $b) {
                        return $b['profit'] <=> $a['profit'];
                    });
                    foreach ($result['all_points'] as $point): 
                    $is_optimal = ($result['optimal'] && $point['x1'] == $result['optimal']['x1'] && $point['x2'] == $result['optimal']['x2']);
                    ?>
                    <tr class="<?php echo $is_optimal ? 'optimal' : ''; ?>">
                        <td><?php echo $point['x1']; ?></td>
                        <td><?php echo $point['x2']; ?></span></td>
                        <td>MYR<?php echo number_format($point['profit'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
		
        <!-- Problem Formulation -->
        <div class="card" style="margin-top: 20px;">
            <h2>Problem Formulation</h2>
            <?php if ($result): ?>
            <p><strong>Decision Variables:</strong><br>
            x = Number of Tables<br>
            y = Number of Chairs</p>
            
            <p><strong>Objective Function:</strong><br>
            Maximize Z = <?php echo $result['profit_table']; ?>x₁ + <?php echo $result['profit_chair']; ?>x₂</p>
            
            <p><strong>Constraints:</strong><br>
            <?php echo $result['hours_table']; ?>x₁ + <?php echo $result['hours_chair']; ?>x₂ <?php echo $result['constraint1_sign']; ?> <?php echo $result['total_hours']; ?><br>
            <?php echo $result['wood_table']; ?>x₁ + <?php echo $result['wood_chair']; ?>x₂ <?php echo $result['constraint2_sign']; ?> <?php echo $result['total_wood']; ?><br>
            x ≥ 0, y ≥ 0</p>
            
            <p><strong>Optimal Solution:</strong><br>
            Tables = <?php echo $result['optimal']['x1']; ?>, Chairs = <?php echo $result['optimal']['x2']; ?>, Maximum Profit = $<?php echo number_format($result['optimal']['profit'], 2); ?></p>
            <?php else: ?>
            <p><strong>Decision Variables:</strong><br>
            x = Number of Tables<br>
            y = Number of Chairs</p>
            
            <p><strong>Objective Function:</strong><br>
            Maximize Z = 50x + 30y</p>
            
            <p><strong>Constraints:</strong><br>
            2x + 3y ≤ 100 (Hours)<br>
            4x + 2y ≤ 120 (Wood)<br>
            x ≥ 0, y ≥ 0</p>
            
            <p><strong>Optimal Solution:</strong><br>
            Tables = 20, Chairs = 20, Maximum Profit = 1,600 MYR</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>