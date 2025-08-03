class HcaptchaManager {
    constructor(config) {
      this.config = {
        siteKey: config.siteKey || '1eb25e26-63d0-476a-bcb6-ae62a2b04752',
        formSelector: config.formSelector || 'form',
        resultsId: config.resultsId || 'whois-results',
        timeout: config.timeout || 120000, // Increased timeout to 120 seconds (2 minutes)
        maxRetries: config.maxRetries || 3, // Maximum number of retries
        retryDelay: config.retryDelay || 5000, // Delay between retries (5 seconds)
        ...config
      };
      this.hcaptchaResponse = null;
      this.hcaptchaReadyPromise = new Promise((resolve, reject) => {
        this.hcaptchaPromiseResolve = resolve;
        this.hcaptchaPromiseReject = reject;
      });
  
      window.hcaptchaVerifyCallback = (response) => {
        this.hcaptchaResponse = response;
        this.hcaptchaPromiseResolve();
      };
  
      this.setupTimeout();
    }
  
    setupTimeout() {
      setTimeout(() => {
        if (!this.hcaptchaResponse) {
          console.error('hCaptcha verification timed out after', this.config.timeout / 1000, 'seconds');
          this.hcaptchaPromiseReject(new Error('hCaptcha verification timed out after ' + (this.config.timeout / 1000) + ' seconds'));
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
  
      for (let attempt = 1; attempt <= this.config.maxRetries; attempt++) {
        try {
          // Reset the promise for each attempt
          this.hcaptchaReadyPromise = new Promise((resolve, reject) => {
            this.hcaptchaPromiseResolve = resolve;
            this.hcaptchaPromiseReject = reject;
          });
  
          // Reset the timeout for each attempt
          this.setupTimeout();
  
          // Execute hCaptcha
          hcaptcha.execute();
          await this.hcaptchaReadyPromise;
          return true;
        } catch (err) {
          console.error(`hCaptcha execution failed on attempt ${attempt}:`, err);
          if (attempt === this.config.maxRetries) {
            return false;
          }
          // Wait before retrying
          await new Promise(resolve => setTimeout(resolve, this.config.retryDelay));
        }
      }
  
      return false; // This line should never be reached due to the return in the loop
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