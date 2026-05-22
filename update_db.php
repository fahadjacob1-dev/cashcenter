<?php
require_once 'config.php';

$sql = "
CREATE TABLE IF NOT EXISTS istilam_bags (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    istilam_id INT UNSIGNED NOT NULL,
    bag_num SMALLINT UNSIGNED NOT NULL,
    d50000 INT UNSIGNED NOT NULL DEFAULT 0,
    d25000 INT UNSIGNED NOT NULL DEFAULT 0,
    d10000 INT UNSIGNED NOT NULL DEFAULT 0,
    d5000 INT UNSIGNED NOT NULL DEFAULT 0,
    d1000 INT UNSIGNED NOT NULL DEFAULT 0,
    d500 INT UNSIGNED NOT NULL DEFAULT 0,
    d250 INT UNSIGNED NOT NULL DEFAULT 0,
    total_amount DECIMAL(18,3) NOT NULL DEFAULT 0,
    FOREIGN KEY (istilam_id) REFERENCES istilam(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sahb_bags (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sahb_id INT UNSIGNED NOT NULL,
    bag_num SMALLINT UNSIGNED NOT NULL,
    d50000 INT UNSIGNED NOT NULL DEFAULT 0,
    d25000 INT UNSIGNED NOT NULL DEFAULT 0,
    d10000 INT UNSIGNED NOT NULL DEFAULT 0,
    d5000 INT UNSIGNED NOT NULL DEFAULT 0,
    d1000 INT UNSIGNED NOT NULL DEFAULT 0,
    d500 INT UNSIGNED NOT NULL DEFAULT 0,
    d250 INT UNSIGNED NOT NULL DEFAULT 0,
    total_amount DECIMAL(18,3) NOT NULL DEFAULT 0,
    FOREIGN KEY (sahb_id) REFERENCES sahb(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if ($conn->multi_query($sql)) {
    do {
        if ($res = $conn->store_result()) {
            $res->free();
        }
    } while ($conn->more_results() && $conn->next_result());
    echo "<h1 style='color:green;text-align:center;'>تم تحديث قاعدة البيانات وإضافة جداول الأكياس بنجاح!</h1>";
} else {
    echo "<h1 style='color:red;text-align:center;'>حدث خطأ: " . $conn->error . "</h1>";
}
?>
