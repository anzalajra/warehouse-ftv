<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Role;

class SetupController extends Controller
{
    public function index()
    {
        $requirements = [
            'PHP 8.4.27+ / 8.5.12+' => (PHP_VERSION_ID >= 80427 && PHP_VERSION_ID < 80500)
                || (PHP_VERSION_ID >= 80512 && PHP_VERSION_ID < 80600),
            'BCMath' => extension_loaded('bcmath'),
            'Ctype' => extension_loaded('ctype'),
            'JSON' => extension_loaded('json'),
            'Mbstring' => extension_loaded('mbstring'),
            'OpenSSL' => extension_loaded('openssl'),
            'PDO' => extension_loaded('pdo'),
            'Tokenizer' => extension_loaded('tokenizer'),
            'XML' => extension_loaded('xml'),
            'Fileinfo' => extension_loaded('fileinfo'),
        ];

        $allMet = !in_array(false, $requirements);

        return view('setup.index', compact('requirements', 'allMet'));
    }

    public function step1()
    {
        return view('setup.step1');
    }

    public function step2(Request $request)
    {
        $request->validate([
            'db_host' => 'required',
            'db_port' => 'required',
            'db_database' => 'required',
            'db_username' => 'required',
            // db_password can be empty
        ]);

        // Test connection
        try {
            $connection = new \PDO(
                "mysql:host={$request->db_host};port={$request->db_port};dbname={$request->db_database}",
                $request->db_username,
                $request->db_password
            );
            $connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        } catch (\Exception $e) {
            return back()->withErrors(['connection' => 'Could not connect to the database: ' . $e->getMessage()])->withInput();
        }

        // Write to .env
        $this->updateEnv([
            'DB_HOST' => $request->db_host,
            'DB_PORT' => $request->db_port,
            'DB_DATABASE' => $request->db_database,
            'DB_USERNAME' => $request->db_username,
            'DB_PASSWORD' => $request->db_password,
        ]);

        return redirect()->route('setup.step3');
    }

    public function step3()
    {
        return view('setup.step3');
    }

    public function step4()
    {
        // Run migrations
        try {
            Artisan::call('migrate:fresh', ['--force' => true]);
            Artisan::call('db:seed', ['--force' => true]);
            Artisan::call('key:generate', ['--force' => true]);
            Artisan::call('storage:link');
        } catch (\Exception $e) {
            return back()->withErrors(['migration' => 'Migration failed: ' . $e->getMessage()]);
        }

        return redirect()->route('setup.step5');
    }

    public function step5()
    {
        return view('setup.step5');
    }

    public function step6(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email',
            'password' => 'required|confirmed|min:8',
        ]);

        // Create Admin User
        try {
            DB::beginTransaction();

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'email_verified_at' => now(),
            ]);

            // Ensure super_admin role exists
            $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
            
            // Assign role
            $user->assignRole($role);

            // Clear permission cache to ensure immediate effect
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

            DB::commit();
        } catch (\Exception $e) {
             DB::rollBack();
             return back()->withErrors(['user' => 'Failed to create user: ' . $e->getMessage()]);
        }
        
        // Create installed lock file
        File::put(storage_path('installed'), 'Installed on ' . now());

        return redirect()->route('home');
    }

    protected function updateEnv($data = [])
    {
        $path = base_path('.env');

        if (!File::exists($path)) {
            if (File::exists(base_path('.env.example'))) {
                File::copy(base_path('.env.example'), $path);
            } else {
                File::put($path, '');
            }
        }

        $envContent = File::get($path);

        foreach ($data as $key => $value) {
            // If value contains spaces, quote it
            if (str_contains($value, ' ')) {
                $value = '"' . $value . '"';
            }
            
            // Check if key exists
            if (preg_match("/^{$key}=/m", $envContent)) {
                // Update existing key
                $envContent = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $envContent);
            } else {
                // Add new key
                $envContent .= "\n{$key}={$value}";
            }
        }

        File::put($path, $envContent);
    }
}
