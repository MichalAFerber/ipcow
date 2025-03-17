class HcaptchaManager {
    constructor(config) {
        this.config = {
            siteKey: config.siteKey || '1eb25e26-63d0-476a-bcb6-ae62a2b04752',
            formSelector: config.formSelector || 'form',
            resultsId: config.resultsId || 'whois-results',
            timeout: config.timeout || 60000,
            ...config
        };
        this.hcaptchaResponse = null;
        this.hcaptchaReadyPromise = new Promise((resolve, reject) => {
            this.hcaptchaPromiseResolve = resolve;
            this.hcaptchaPromiseReject = reject;
        });

        window.hcaptchaVerifyCallback = (response) => {
            //console.log('hCaptcha verified with response:', response);
            this.hcaptchaResponse = response;
            this.hcaptchaPromiseResolve();
        };

        this.setupTimeout();
    }

    setupTimeout() {
        setTimeout(() => {
            if (!this.hcaptchaResponse) {
                const resultsDiv = document.getElementById(this.config.resultsId);
                if (resultsDiv) resultsDiv.innerHTML = 'Error: hCaptcha verification timed out. Please try again.';
                console.error('hCaptcha verification timed out after', this.config.timeout / 1000, 'seconds');
                this.hcaptchaPromiseReject(new Error('hCaptcha verification timed out'));
                hcaptcha.reset();
            }
        }, this.config.timeout);
    }

    async executeHcaptcha() {
        const resultsDiv = document.getElementById(this.config.resultsId);
        if (!resultsDiv) {
            console.error('Results div not found');
            return false;
        }

        resultsDiv.innerHTML = 'Verifying hCaptcha...';
        console.log('Executing invisible hCaptcha challenge');

        try {
            hcaptcha.execute();
            await this.hcaptchaReadyPromise;
            console.log('hCaptcha ready, proceeding with submission');
            return true;
        } catch (err) {
            console.error('hCaptcha execution failed:', err);
            resultsDiv.innerHTML = 'Error: Failed to execute hCaptcha. Please refresh and try again.';
            return false;
        }
    }

    getHcaptchaResponse() {
        return this.hcaptchaResponse;
    }

    // Make reset publicly accessible and reset internal state
    resetHcaptcha() {
        this.hcaptchaResponse = null;
        this.hcaptchaReadyPromise = new Promise((resolve, reject) => {
            this.hcaptchaPromiseResolve = resolve;
            this.hcaptchaPromiseReject = reject;
        });
        hcaptcha.reset();
        this.setupTimeout();
    }
}

window.HcaptchaManager = HcaptchaManager;