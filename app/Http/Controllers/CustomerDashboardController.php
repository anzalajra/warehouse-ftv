<?php

namespace App\Http\Controllers;

use App\Helpers\WhatsAppHelper;
use App\Models\DocumentType;
use App\Models\Rental;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rules;

class CustomerDashboardController extends Controller
{
    public function index()
    {
        $customer = Auth::guard('customer')->user();
        $activeRentals = $customer->getActiveRentals();
        $pastRentals = $customer->getPastRentals();
        $cartCount = $customer->carts()->count();
        $verificationStatus = $customer->getVerificationStatus();

        return view('frontend.dashboard.index', compact('customer', 'activeRentals', 'pastRentals', 'cartCount', 'verificationStatus'));
    }

    public function profile()
    {
        $customer = Auth::guard('customer')->user();
        $documentTypes = DocumentType::getActiveTypes($customer->customer_category_id);
        $uploadedDocuments = $customer->documents()->with('documentType')->get()->keyBy('document_type_id');
        $verificationStatus = $customer->getVerificationStatus();
        
        $customFields = json_decode(\App\Models\Setting::get('registration_custom_fields', '[]'), true);

        return view('frontend.dashboard.profile', compact('customer', 'documentTypes', 'uploadedDocuments', 'verificationStatus', 'customFields'));
    }

    public function updateProfile(Request $request)
    {
        $customer = Auth::guard('customer')->user();

        // Base validation
        $rules = [
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'nik' => 'required|string|size:16',
            'address' => 'nullable|string',
        ];

        // Custom fields validation
        $customFields = json_decode(\App\Models\Setting::get('registration_custom_fields', '[]'), true);
        $customData = $customer->custom_fields ?? [];
        
        if (!empty($customFields)) {
            foreach ($customFields as $field) {
                // Check visibility for current customer category
                $visibleCategories = $field['visible_for_categories'] ?? [];
                if (!empty($visibleCategories) && !in_array($customer->customer_category_id, $visibleCategories)) {
                    continue; 
                }

                $fieldName = 'custom_' . $field['name'];
                $fieldRules = [];

                if ($field['required'] ?? false) {
                    $fieldRules[] = 'required';
                } else {
                    $fieldRules[] = 'nullable';
                }

                if ($field['type'] === 'number') {
                    $fieldRules[] = 'numeric';
                } elseif ($field['type'] === 'email') {
                    $fieldRules[] = 'email';
                } else {
                    $fieldRules[] = 'string';
                }

                $rules[$fieldName] = $fieldRules;
            }
        }

        $validated = $request->validate($rules);

        // Extract custom fields data
        if (!empty($customFields)) {
            foreach ($customFields as $field) {
                $fieldName = 'custom_' . $field['name'];
                
                // Only update if the field was present in the request (it might be hidden/not submitted)
                // However, since we validate it (if visible), it should be in $validated if we add it to rules
                // But if it was skipped in validation loop above (not visible), we shouldn't update it (keep old value or null)
                
                $visibleCategories = $field['visible_for_categories'] ?? [];
                if (!empty($visibleCategories) && !in_array($customer->customer_category_id, $visibleCategories)) {
                    continue; 
                }

                if (array_key_exists($fieldName, $validated)) {
                    $customData[$field['name']] = $validated[$fieldName];
                }
            }
        }

        $customer->name = $validated['name'];
        $customer->phone = $validated['phone'];
        $customer->nik = $validated['nik'];
        $customer->address = $validated['address'];
        $customer->custom_fields = $customData;
        $customer->save();

        return back()->with('success', 'Profile updated successfully.');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $customer = Auth::guard('customer')->user();

        if (!Hash::check($request->current_password, $customer->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        $customer->update([
            'password' => Hash::make($request->password),
        ]);

        return back()->with('success', 'Password updated successfully.');
    }

    public function rentals()
    {
        $customer = Auth::guard('customer')->user();
        $rentals = $customer->rentals()->with(['items.productUnit.product'])->orderBy('created_at', 'desc')->paginate(10);

        return view('frontend.dashboard.rentals', compact('rentals'));
    }

    public function rentalDetail($id)
    {
        $customer = Auth::guard('customer')->user();
        $rental = $customer->rentals()
            ->with(['items.productUnit.product', 'items.rentalItemKits.unitKit', 'deliveries'])
            ->findOrFail($id);

        $checklistSteps = $rental->getChecklistSteps();
        $warehousePhone = Setting::get('warehouse_whatsapp_number', Setting::get('whatsapp_number'));
        $permitLink = Setting::get('permit_document_link', '#');

        $waMessage = $this->buildWaMessage($customer, $rental);
        $waLink = WhatsAppHelper::getLink($warehousePhone, $waMessage);

        $checklistPdfUrl = URL::signedRoute('public-documents.rental.checklist', ['rental' => $rental]);

        return view('frontend.dashboard.rental-detail', compact(
            'rental', 'checklistSteps', 'waLink', 'permitLink', 'checklistPdfUrl'
        ));
    }

    public function markChecklistDownloaded(Rental $rental)
    {
        $customer = Auth::guard('customer')->user();
        if ($rental->user_id != $customer->id) {
            abort(403);
        }

        if (!$rental->checklist_downloaded_at) {
            $rental->update(['checklist_downloaded_at' => now()]);
        }

        return response()->json(['success' => true]);
    }

    public function markPermitClicked(Rental $rental)
    {
        $customer = Auth::guard('customer')->user();
        if ($rental->user_id != $customer->id) {
            abort(403);
        }

        if (!$rental->permit_template_clicked_at) {
            $rental->update(['permit_template_clicked_at' => now()]);
        }

        return response()->json(['success' => true]);
    }

    private function buildWaMessage($customer, Rental $rental): string
    {
        $defaultTemplate = "Halo admin warehouse, saya [customer_name] ingin konfirmasi booking [rental_code].\n\nMohon konfirmasi booking:\n[admin_url]";
        $template = Setting::get('warehouse_wa_template', $defaultTemplate);

        return str_replace(
            ['[customer_name]', '[rental_code]', '[admin_url]'],
            [$customer->name, $rental->rental_code, route('filament.admin.resources.rentals.view', $rental)],
            $template
        );
    }
}