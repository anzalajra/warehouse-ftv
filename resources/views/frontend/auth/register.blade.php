@extends('layouts.frontend')

@section('title', 'Register')

@section('content')
@php
    $oldCategoryId = old('customer_category_id');
    
    // Use default category if set and no old input
    // if (!$oldCategoryId && isset($defaultCategoryId) && $defaultCategoryId) {
    //     $oldCategoryId = $defaultCategoryId;
    // }

    $oldCategory = $oldCategoryId ? $categories->find($oldCategoryId) : null;
    $umumId = $categories->firstWhere('slug', 'umum')?->id;
    
    $initialStep = $oldCategoryId ? 'form' : 'type_selection';
    $initialAccountType = '';
    if ($oldCategoryId) {
        $initialAccountType = ($oldCategoryId == $umumId) ? 'umum' : 'civitas';
    }
@endphp
<div class="min-h-[60vh] flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8"
    x-data="{
        step: '{{ $initialStep }}',
        accountType: '{{ $initialAccountType }}',
        categoryId: '{{ $oldCategoryId }}',
        
        selectUmum() {
            this.accountType = 'umum';
            this.categoryId = '{{ $umumId }}';
            this.step = 'form';
        },
        
        selectCivitas() {
            this.accountType = 'civitas';
            this.categoryId = ''; // Reset selection
            this.step = 'form';
        },
        
        goBack() {
            this.step = 'type_selection';
        }
    }"
>
    <div class="max-w-md w-full space-y-8">
        
        {{-- Header --}}
        <div>
            <h2 class="text-center text-3xl font-bold text-gray-900">
                <span x-show="step === 'type_selection'">Pilih Jenis Akun</span>
                <span x-show="step === 'form'">Create Account</span>
            </h2>
            <p class="mt-2 text-center text-sm text-gray-600" x-show="step === 'type_selection'">
                Already have an account? <a href="{{ route('customer.login') }}" class="text-primary-600 hover:underline">Sign in</a>
            </p>
        </div>

        {{-- Step 1: Type Selection --}}
        <div x-show="step === 'type_selection'" class="space-y-4 mt-8" x-transition>
            <button @click="selectUmum()" 
                class="w-full group relative flex items-center p-5 bg-white border-2 border-gray-100 rounded-2xl hover:border-primary-500 hover:shadow-lg hover:shadow-primary-500/10 transition-all duration-200 transform hover:-translate-y-1">
                <div class="flex-shrink-0 h-14 w-14 min-w-[3.5rem] flex items-center justify-center rounded-xl bg-primary-50 text-primary-600 group-hover:bg-primary-600 group-hover:text-white transition-colors duration-200">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                </div>
                <div class="ml-4 text-left flex-1">
                    <h3 class="text-lg font-bold text-gray-900 group-hover:text-primary-700">Umum</h3>
                    <p class="text-sm text-gray-500 mt-1">Masyarakat umum non-civitas FTV</p>
                </div>
                <div class="flex-shrink-0 ml-4 text-gray-300 group-hover:text-primary-500 transition-colors duration-200">
                    <svg class="w-6 h-6 transform group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </div>
            </button>

            <button @click="selectCivitas()" 
                class="w-full group relative flex items-center p-5 bg-white border-2 border-gray-100 rounded-2xl hover:border-primary-500 hover:shadow-lg hover:shadow-primary-500/10 transition-all duration-200 transform hover:-translate-y-1">
                <div class="flex-shrink-0 h-14 w-14 min-w-[3.5rem] flex items-center justify-center rounded-xl bg-primary-50 text-primary-600 group-hover:bg-primary-600 group-hover:text-white transition-colors duration-200">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                </div>
                <div class="ml-4 text-left flex-1">
                    <h3 class="text-lg font-bold text-gray-900 group-hover:text-primary-700">Civitas FTV</h3>
                    <p class="text-sm text-gray-500 mt-1">Mahasiswa, Dosen, atau Staff FTV</p>
                </div>
                <div class="flex-shrink-0 ml-4 text-gray-300 group-hover:text-primary-500 transition-colors duration-200">
                    <svg class="w-6 h-6 transform group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </div>
            </button>
        </div>

        {{-- Step 2: Form --}}
        <div x-show="step === 'form'" class="mt-8" x-transition>
            
            <div class="mb-6">
                <template x-if="accountType === 'umum'">
                    <div class="bg-primary-50 border border-primary-200 rounded-lg p-4 flex items-center justify-between">
                        <div>
                            <span class="text-sm text-primary-600 font-medium">Registering as:</span>
                            <span class="ml-2 text-primary-800 font-bold">Umum</span>
                        </div>
                        <button @click="step = 'type_selection'" class="text-sm text-primary-600 hover:text-primary-800 underline">Change</button>
                    </div>
                </template>

                <template x-if="accountType === 'civitas'">
                    <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                        <div class="flex justify-between items-center mb-3">
                            <label class="block text-sm font-medium text-gray-700">I am a:</label>
                            <button @click="step = 'type_selection'" class="text-xs text-gray-500 hover:text-gray-700 underline">Change Type</button>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            @foreach($categories->where('slug', '!=', 'umum') as $category)
                                <button type="button"
                                    @click="categoryId = '{{ $category->id }}'"
                                    :class="categoryId == '{{ $category->id }}' 
                                        ? 'bg-primary-600 text-white border-primary-600 shadow-md ring-2 ring-primary-500 ring-offset-2' 
                                        : 'bg-white text-gray-700 border-gray-200 hover:border-primary-400 hover:bg-gray-50 hover:shadow-sm'"
                                    class="relative flex items-center justify-center space-x-2 px-4 py-3 border rounded-xl focus:outline-none transition-all duration-200">
                                    <span class="font-medium text-sm">{{ $category->name }}</span>
                                    <div x-show="categoryId == '{{ $category->id }}'" x-transition.scale.origin.center>
                                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    </div>
                                </button>
                            @endforeach
                        </div>
                        @error('customer_category_id')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </template>
            </div>

            <form class="space-y-6" action="{{ route('customer.register') }}" method="POST">
                @csrf
                <input type="hidden" name="customer_category_id" :value="categoryId">

                @if($errors->any())
                    <div class="bg-red-50 text-red-600 p-4 rounded-lg text-sm">
                        @foreach($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                @endif

                <div class="space-y-4">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">Full Name</label>
                        <input id="name" name="name" type="text" required value="{{ old('name') }}" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-primary-500 focus:border-primary-500">
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">Email address</label>
                        <input id="email" name="email" type="email" required value="{{ old('email') }}" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-primary-500 focus:border-primary-500">
                    </div>

                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700">Phone Number</label>
                        <input id="phone" name="phone" type="text" required value="{{ old('phone') }}" placeholder="08xxxxxxxxxx" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-primary-500 focus:border-primary-500">
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                        <input id="password" name="password" type="password" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-primary-500 focus:border-primary-500">
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Confirm Password</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-primary-500 focus:border-primary-500">
                    </div>

                    {{-- Custom Fields --}}
                    @if(isset($customFields) && count($customFields) > 0)
                        <div class="space-y-4 pt-4 border-t border-gray-200">
                            @foreach($customFields as $field)
                                @php
                                    $fieldName = 'custom_' . $field['name'];
                                    $visibleCats = $field['visible_for_categories'] ?? [];
                                    // Ensure IDs are strings for consistent JS comparison
                                    $visibleCats = array_map('strval', $visibleCats);
                                    $visibleCatsJson = json_encode($visibleCats);
                                    $isRequired = $field['required'] ?? false;

                                    // Normalize options: stored as comma-separated string from settings repeater
                                    $rawOptions = $field['options'] ?? [];
                                    if (is_string($rawOptions)) {
                                        $rawOptions = array_filter(array_map('trim', explode(',', $rawOptions)), fn($v) => $v !== '');
                                    }
                                    $normalizedOptions = [];
                                    foreach ($rawOptions as $opt) {
                                        if (is_array($opt)) {
                                            $normalizedOptions[] = [
                                                'value' => $opt['value'] ?? ($opt['label'] ?? ''),
                                                'label' => $opt['label'] ?? ($opt['value'] ?? ''),
                                            ];
                                        } else {
                                            $normalizedOptions[] = ['value' => $opt, 'label' => $opt];
                                        }
                                    }
                                @endphp
                                <div x-data="{ visibleCats: {{ $visibleCatsJson }} }" 
                                     x-show="visibleCats.length === 0 || visibleCats.includes(String(categoryId))"
                                     x-transition>
                                    
                                    @if($field['type'] !== 'checkbox')
                                        <label for="{{ $fieldName }}" class="block text-sm font-medium text-gray-700">
                                            {{ $field['label'] }}
                                            @if($isRequired) <span class="text-red-500">*</span> @endif
                                        </label>
                                    @endif
                                    
                                    @if($field['type'] === 'text' || $field['type'] === 'number' || $field['type'] === 'email')
                                        <input id="{{ $fieldName }}" name="{{ $fieldName }}" type="{{ $field['type'] === 'number' ? 'number' : 'text' }}" 
                                            value="{{ old($fieldName) }}"
                                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-primary-500 focus:border-primary-500">
                                    
                                    @elseif($field['type'] === 'textarea')
                                        <textarea id="{{ $fieldName }}" name="{{ $fieldName }}" rows="3" 
                                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-primary-500 focus:border-primary-500">{{ old($fieldName) }}</textarea>
                                    
                                    @elseif($field['type'] === 'select')
                                        <select id="{{ $fieldName }}" name="{{ $fieldName }}" 
                                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-primary-500 focus:border-primary-500">
                                            <option value="">Select {{ $field['label'] }}</option>
                                            @foreach($normalizedOptions as $option)
                                                <option value="{{ $option['value'] }}" {{ old($fieldName) == $option['value'] ? 'selected' : '' }}>
                                                    {{ $option['label'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    
                                    @elseif($field['type'] === 'radio')
                                        <div class="mt-2 space-y-2">
                                            @foreach($normalizedOptions as $option)
                                                <div class="flex items-center">
                                                    <input id="{{ $fieldName }}_{{ $option['value'] }}" name="{{ $fieldName }}" type="radio" value="{{ $option['value'] }}"
                                                        {{ old($fieldName) == $option['value'] ? 'checked' : '' }}
                                                        class="focus:ring-primary-500 h-4 w-4 text-primary-600 border-gray-300">
                                                    <label for="{{ $fieldName }}_{{ $option['value'] }}" class="ml-2 block text-sm text-gray-700">
                                                        {{ $option['label'] }}
                                                    </label>
                                                </div>
                                            @endforeach
                                        </div>

                                    @elseif($field['type'] === 'checkbox')
                                        <div class="mt-2 flex items-center">
                                            <input id="{{ $fieldName }}" name="{{ $fieldName }}" type="checkbox" value="1"
                                                {{ old($fieldName) ? 'checked' : '' }}
                                                class="focus:ring-primary-500 h-4 w-4 text-primary-600 border-gray-300 rounded">
                                            <label for="{{ $fieldName }}" class="ml-2 block text-sm text-gray-700">
                                                {{ $field['label'] }} @if($isRequired) <span class="text-red-500">*</span> @endif
                                            </label>
                                        </div>
                                    @endif
                                    
                                    @error($fieldName)
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <button type="submit" class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                    Create Account
                </button>
            </form>
        </div>
    </div>
</div>
@endsection