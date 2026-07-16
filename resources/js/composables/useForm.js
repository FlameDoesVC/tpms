import { reactive } from 'vue';
import axios from 'axios';

export function useForm(fields) {
    const defaults = { ...fields };

    const form = reactive({
        ...fields,
        errors: {},
        processing: false,
        recentlySuccessful: false,

        reset(...keys) {
            if (keys.length === 0) {
                Object.assign(form, defaults);
            } else {
                keys.forEach((key) => {
                    if (key in defaults) form[key] = defaults[key];
                });
            }
            form.errors = {};
        },

        clearErrors() {
            form.errors = {};
        },

        async submit(method, url, options = {}) {
            form.processing = true;
            form.errors = {};

            const data = {};
            for (const key of Object.keys(defaults)) {
                data[key] = form[key];
            }

            try {
                const response = await axios({ method, url, data });
                form.recentlySuccessful = true;
                setTimeout(() => (form.recentlySuccessful = false), 2000);
                if (options.onSuccess) options.onSuccess(response);
                return response;
            } catch (error) {
                if (error.response?.status === 422) {
                    form.errors = error.response.data.errors || {};
                    // Flatten to single message per field
                    for (const key in form.errors) {
                        if (Array.isArray(form.errors[key])) {
                            form.errors[key] = form.errors[key][0];
                        }
                    }
                }
                if (options.onError) options.onError(error);
                if (options.onFinish) options.onFinish();
                throw error;
            } finally {
                form.processing = false;
                if (options.onFinish) options.onFinish();
            }
        },

        post(url, options = {}) {
            return form.submit('post', url, options);
        },

        put(url, options = {}) {
            return form.submit('put', url, options);
        },

        patch(url, options = {}) {
            return form.submit('patch', url, options);
        },

        delete(url, options = {}) {
            return form.submit('delete', url, options);
        },
    });

    return form;
}
