// Sends the selected PDFs in small sequential batches, because a single request
// must fit nginx client_max_body_size / PHP post_max_size (50M) and max_file_uploads (20).
// Resolves to the same shape as one bulk-upload response, with all batches merged.
window.uploadPdfsInBatches = async function(projectId, files, onProgress) {
    const MAX_BATCH_BYTES = 40 * 1024 * 1024;
    const MAX_BATCH_FILES = 20;
    const MAX_FILE_BYTES = 50 * 1024 * 1024 - 64 * 1024; // leaves room for multipart overhead

    const token = document.querySelector('meta[name="csrf-token"]').content;
    const results = { assigned: [], unassigned: [] };
    const failed = [];

    const batches = [];
    let batch = [];
    let batchBytes = 0;
    Array.from(files).forEach(file => {
        if (file.size > MAX_FILE_BYTES) {
            failed.push(`${file.name}: larger than 50 MB`);
            return;
        }
        if (batch.length > 0 && (batch.length >= MAX_BATCH_FILES || batchBytes + file.size > MAX_BATCH_BYTES)) {
            batches.push(batch);
            batch = [];
            batchBytes = 0;
        }
        batch.push(file);
        batchBytes += file.size;
    });
    if (batch.length > 0) {
        batches.push(batch);
    }

    const total = batches.reduce((sum, current) => sum + current.length, 0);
    let processed = 0;
    let sessionExpired = false;

    for (const current of batches) {
        if (sessionExpired) {
            current.forEach(file => failed.push(`${file.name}: session expired`));
            continue;
        }

        const formData = new FormData();
        current.forEach(file => formData.append('pdfs[]', file));
        formData.append('_token', token);

        let reason = null;
        try {
            const response = await fetch(`/projects/${projectId}/studies/bulk-upload`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                body: formData
            });
            const data = await response.json().catch(() => null);

            if (response.ok && data && data.success) {
                results.assigned.push(...data.results.assigned);
                results.unassigned.push(...data.results.unassigned);
            } else if (response.status === 401 || response.status === 419) {
                sessionExpired = true;
                reason = 'session expired';
            } else {
                reason = (data && data.message) || `HTTP ${response.status}`;
            }
        } catch (error) {
            console.error('Bulk upload batch failed:', error);
            reason = 'network error';
        }

        if (reason) {
            current.forEach(file => failed.push(`${file.name}: ${reason}`));
        }

        processed += current.length;
        if (onProgress) {
            onProgress(processed, total);
        }
    }

    const uploaded = results.assigned.length + results.unassigned.length;

    if (failed.length > 0) {
        console.error('Bulk upload failures:', failed);
    }

    if (uploaded === 0) {
        return { success: false, message: failed.slice(0, 5).join('; ') || 'No files were uploaded' };
    }

    if (failed.length > 0) {
        const shown = failed.slice(0, 20).join('\n');
        const hidden = failed.length > 20 ? `\n...and ${failed.length - 20} more (see browser console)` : '';
        alert(`${uploaded} PDF(s) uploaded, but ${failed.length} failed. Upload these again:\n\n${shown}${hidden}`);
    }

    return { success: true, results: results };
};

