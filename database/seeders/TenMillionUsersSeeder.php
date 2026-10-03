<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TenMillionUsersSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Initiating high-speed MySQL side data generation (Target: 10M rows)...');
        $startTime = microtime(true);

        // 1. Drop safety constraints during bulk ingestion
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::statement('SET UNIQUE_CHECKS=0;');
        DB::statement('SET AUTOCOMMIT=0;');

        // 2. Execute a corrected native MySQL 8 loop via an anonymous procedure
        DB::unprepared("
            DROP PROCEDURE IF EXISTS FastUserSeed;
            CREATE PROCEDURE FastUserSeed()
            BEGIN
                DECLARE i INT DEFAULT 0;
                WHILE i < 10000000 DO
                    -- Corrected cross join: 10 * 10 * 10 * 10 * 10 = 100,000 rows per loop
                    INSERT INTO users (first_name, last_name, email, password, created_at, updated_at)
                    SELECT 
                        CONCAT('UserFirst', seq),
                        CONCAT('UserLast', seq),
                        CONCAT('user.', seq, '@example.com'),
                        CONCAT('user.', seq),
                        CAST(9000000000 + seq AS CHAR),
                        NOW(),
                        NOW()
                    FROM (
                        SELECT @row := @row + 1 AS seq 
                        FROM (SELECT 1 U1 UNION SELECT 2 U2 UNION SELECT 3 U3 UNION SELECT 4 U4 UNION SELECT 5 U5 UNION SELECT 6 U6 UNION SELECT 7 U7 UNION SELECT 8 U8 UNION SELECT 9 U9 UNION SELECT 10 U10) t1
                        CROSS JOIN (SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10) t2
                        CROSS JOIN (SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10) t3
                        CROSS JOIN (SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10) t4
                        CROSS JOIN (SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10) t5
                        CROSS JOIN (SELECT @row := i) r
                    ) AS sub_seq;
                    
                    SET i = i + 100000;
                    COMMIT; -- Flush memory chunk to InnoDB log lines
                END WHILE;
            END;
        ");

        // Execute the procedure
        DB::unprepared('CALL FastUserSeed();');
        DB::unprepared('DROP PROCEDURE IF EXISTS FastUserSeed;');

        // 3. Restore safety constraints
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        DB::statement('SET UNIQUE_CHECKS=1;');
        DB::statement('COMMIT;');

        $duration = round(microtime(true) - $startTime, 2);
        $this->command->info("Successfully seeded 10,000,000 rows in {$duration} seconds!");
    }
}
