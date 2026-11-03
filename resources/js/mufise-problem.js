export function mufiseProblemText(problem) {
    if (!problem) return '';
    const guidance = {
        check_configuration: 'Hubungi admin Warehouse untuk memeriksa pengaturan koneksi.',
        check_credentials: 'Hubungi admin Warehouse untuk memeriksa konfigurasi dan token.',
        check_token_storage: 'Hubungi admin Warehouse untuk memeriksa penyimpanan token.',
        use_server: 'Hubungi admin Warehouse untuk memperbaiki koneksi server.',
        verify_email: 'Buka Mufise untuk memverifikasi email, lalu periksa kembali.',
        contact_admin: 'Hubungi admin Mufise atau organisasi pemilik koneksi.',
        review_permissions: 'Periksa izin pengguna serta akses Production dan Plan di Mufise.',
        review_resource: 'Perbarui daftar proyek dan periksa akses akun.',
        reload_data: 'Muat ulang data dan tinjau perubahan sebelum menyimpan operasi baru.',
        review_operation: 'Tinjau data operasi sebelum mengirim ulang.',
        correct_request: 'Periksa data formulir sebelum mencoba lagi.',
        join_organization: 'Mulai pemasangan baru untuk melengkapi keanggotaan organisasi.',
        reconnect_account: 'Hubungkan ulang akun Mufise.',
        restart_pairing: 'Mulai pemasangan baru.',
        retry_later: `Coba lagi setelah ${problem.retry_after || 30} detik.`,
    }[problem.action] || '';
    return [problem.message, guidance,
        problem.request_id ? `Referensi Mufise: ${problem.request_id}` : '',
        problem.warehouse_request_id ? `Referensi Warehouse: ${problem.warehouse_request_id}` : '',
    ].filter(Boolean).join(' ');
}

export function canPairMufise(problem) {
    return !problem || ['reconnect_account', 'restart_pairing', 'join_organization'].includes(problem.action)
        || problem.reason === 'local_token_expired';
}
