/**
 * Create the PayBear UI
 *
 * @param {string|HTMLElement} targetElement - The element to inject the UI into
 * @param {string} actionTrigger - Crafts action trigger
 * @param {[string,string]} csrf - Crafts CSRF details
 * @constructor
 */
function PayBear (targetElement, actionTrigger, csrf) {
	if (window.paybear instanceof PayBear) {
		console.error("You have already initialized PayBear!");
		return;
	}
	
	// Variables
	
	this.element =
		typeof targetElement === typeof ""
			? document.querySelector(targetElement)
			: targetElement;
	this.actionTrigger = actionTrigger;
	this.csrf = csrf;
	
	this.spinner = null;
	
	this.state = this._createState();
	
	this.element.classList.add("paybear--container");
	this._render();
	
	window.paybear = this;
	return this;
}

// Actions
// =============================================================================

// Actions: Public
// -----------------------------------------------------------------------------

/**
 * Sets the amount to charge. Will reset the UI.
 *
 * @param {Number} amount - The amount to charge
 */
PayBear.prototype.setAmount = function (amount) {
	this.state = this._createState();
	this.setState({ amount: amount });
	this._getCurrencies();
};

// Actions: Private
// -----------------------------------------------------------------------------

/**
 * The default UI state
 *
 * @private
 */
PayBear.prototype._createState = function () {
	return {
		step: 0,
		amount: 0,
		currencies: [],
		
		address: '',
		selectedCurrency: null,
		rateLockStartTime: null,
		
		activePaymentMethod: "copy",
	};
};

/**
 * Update the state, passing the new state variables
 *
 * @param {Object} nextState
 */
PayBear.prototype.setState = function (nextState) {
	this.state = Object.assign(this.state, nextState);
	this._onStateChange(Object.keys(nextState));
};

/**
 * Updates the state with the available currencies
 *
 * @private
 */
PayBear.prototype._getCurrencies = function () {
	const self = this;
	this._action("currency", {}, function (res) {
		self.setState({
			currencies: Object.keys(res).map(function (k) { return res[k]; })
		});
	});
};

/**
 * Starts the rate lock timer
 *
 * @private
 */
PayBear.prototype._startLockTimer = function () {
	if (!this.countDown)
		throw new Error("Tried to start lock timer without count down element!");
	
	const self = this;
	this.countDown.textContent = "15:00";
	
	let duration = 60 * 15;
	const timer = function () {
		if (duration <= 0) {
			// TODO: show expired screen
			clearInterval(self._lockTimer);
		}
		
		let seconds = parseInt(duration % 60, 10),
			minutes = parseInt(duration / 60, 10);
		
		seconds = (seconds < 10 ? '0' : '') + seconds;
		minutes = (minutes < 10 ? '0' : '') + minutes;
		
		self.countDown.textContent = minutes + ":" + seconds;
		
		--duration;
	};
	
	this._lockTimer && clearInterval(this._lockTimer);
	this._lockTimer = setInterval(timer, 1000);
};

// Events
// =============================================================================

/**
 * Triggered when the state changes
 *
 * @private
 */
PayBear.prototype._onStateChange = function (changedKeys) {
	if (changedKeys.length === 1 && changedKeys[0] === "activePaymentMethod") {
		this._renderPaymentMethods();
		return;
	}
	
	this._render();
};

/**
 * Triggered when the user clicks the "Back" button
 *
 * @param {Event} e
 * @private
 */
PayBear.prototype._onBack = function (e) {
	e.preventDefault();
	
	this.setState({
		step: 0,
		
		address: '',
		selectedCurrency: null,
		rateLockStartTime: null,
		
		activePaymentMethod: "copy",
	});
	
	this._getCurrencies();
};

/**
 * Triggered when the user selects a currency
 *
 * @param {Object} currency
 * @param {Event} e
 * @private
 */
PayBear.prototype._onSelectCurrency = function (currency, e) {
	e.preventDefault();
	const self = this;
	
	this._action("payment", { code: currency.code }, function (res) {
		self.setState({
			step: 1,
			
			address: res.address,
			selectedCurrency: res.currency,
			rateLockStartTime: new Date(),
		});
		
		self._startLockTimer();
	});
};

/**
 * Triggered when the user switches to a new payment method
 *
 * @param {string} method
 * @param {Event} e
 * @private
 */
PayBear.prototype._onPaymentMethodSwitchClick = function (method, e) {
	e.preventDefault();
	this.setState({
		activePaymentMethod: method,
	});
};

// Render
// =============================================================================

/**
 * Render the UI
 *
 * @private
 */
PayBear.prototype._render = function () {
	const self = this;
	
	// Clear
	while (this.element.firstElementChild)
		this.element.removeChild(this.element.firstElementChild);
	
	// Add Spinner
	this.spinner = PayBear.c("div", { "class": "paybear--spinner" });
	this.spinner.show = function () { self.spinner.classList.add("show"); };
	this.spinner.hide = function () { self.spinner.classList.remove("show"); };
	this.element.appendChild(this.spinner);
	
	// Render
	this["_renderStep" + this.state.step]().forEach(function (node) {
		if (Array.isArray(node)) {
			node.forEach(function (n) {
				self.element.appendChild(n);
			});
			return;
		}
		
		self.element.appendChild(node);
	});
};

/**
 * Renders the grid of currencies
 *
 * @return {*[]}
 * @private
 */
PayBear.prototype._renderStep0 = function () {
	const c = PayBear.c
		, self = this;
	
	return [
		c("p", {}, "Select the currency you’d like to pay with"),
		c(
			"ul",
			{ "class": "paybear--currencies" },
			this.state.currencies.map(function (currency) {
				return c("li", {}, [
					c("button", {
						"click": self._onSelectCurrency.bind(self, currency)
					}, [
						c("img", { src: currency.icon, alt: currency.title }),
						c("span", {
							"class": "paybear--currencies-code"
						}, currency.code),
						c("span", {
							"class": "paybear--currencies-title"
						}, currency.title),
						c(
							"span",
							{ "class": "paybear--currencies-rate" },
							"1 " + currency.code + " = $" + currency.rate + " USD"
						),
					]),
				]);
			})
		),
	];
};

/**
 * Renders the payment screen
 *
 * @return {*[]}
 * @private
 */
PayBear.prototype._renderStep1 = function () {
	const c = PayBear.c;
	
	this.countDown = c("div", { "class": "paybear--pay-header-timer" }, "15:00");
	
	this.paymentMethods = c("div", { "class": "paybear--pay-methods" });
	this._renderPaymentMethods();
	
	const curr = this.state.selectedCurrency;
	
	return [
		c("header", { "class": "paybear--pay-header" }, [
			this.countDown,
			c("div", { "class": "paybear--pay-header-lock" }, [
				"Waiting on Payment",
				c(
					"small",
					{},
					"Rate locked: 1 " + curr.code + " = $" + curr.rate + " USD"
				)
			]),
		]),
		
		c("button", {
			"click": this._onBack.bind(this),
		}, "← Back"),
		
		c("div", { "class": "paybear--pay-amount" }, [
			c("img", { src: curr.icon, alt: curr.title }),
			c("div", {
				"class": "paybear--pay-amount-total"
			}, (this.state.amount / curr.mid).toFixed(8) + " " + curr.code),
		]),
		
		this.paymentMethods,
	];
};

PayBear.prototype._renderPaymentMethods = function () {
	const c = PayBear.c
		, self = this;
	
	while (this.paymentMethods.firstElementChild)
		this.paymentMethods.removeChild(this.paymentMethods.firstElementChild);
	
	const active = this.state.activePaymentMethod
		, curr = this.state.selectedCurrency;
	
	let method = null;
	
	switch (active.toLowerCase()) {
		case "wallet":
			method = c("div", {}, "Wallet");
			break;
		case "copy":
			method = c("div", {
				"class": "paybear--pay-methods-copy"
			}, [
				c("button", {}, "Copy Address"),
				c("button", {}, "Copy Amount"),
			]);
			break;
		case "scan":
			method = c("div", {}, "Scan");
			break;
	}
	
	[
		c(
			"div",
			{ "class": "paybear--pay-methods-switch" },
			["Wallet", "Copy", "Scan"].map(function (method) {
				const handle = method.toLowerCase();
				
				return c("button", {
					"class": active === handle ? "active" : "",
					"click": self._onPaymentMethodSwitchClick.bind(self, handle),
				}, method);
			})
		),
		c("p", {}, "Please send " + curr.title + " to this address"),
		c("p", { "class": "paybear--pay-methods-address" }, this.state.address),
		method,
	].forEach(function (node) {
		self.paymentMethods.appendChild(node);
	});
};

// Helpers
// =============================================================================

/**
 * Get request to the Commerce PayBear action
 *
 * @param {string} handle - The action handle
 * @param {Object} body - The post data
 * @param {Function} callback - The callback function
 * @private
 */
PayBear.prototype._action = function (handle, body, callback) {
	this.spinner.show();
	const xhr = new XMLHttpRequest()
		, self = this;
	xhr.open("POST", this.actionTrigger + "/CommercePayBear/" + handle, true);
	xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
	xhr.onload = function () {
		try {
			callback(JSON.parse(xhr.responseText));
		} catch (e) {
			// TODO: Show error screen
		}
		self.spinner.hide();
	};
	xhr.onerror = function () {
		// TODO: Show error screen
		self.spinner.hide();
	};
	const fd = new FormData();
	fd.append(this.csrf[0], this.csrf[1]);
	Object.entries(body).forEach(function (a) {
		fd.append(a[0], a[1]);
	});
	xhr.send(fd);
};

/**
 * Polyfill `Object.entries`
 *
 * @see https://github.com/KhaledElAnsari/Object.entries
 */
Object.entries = Object.entries ? Object.entries : function (obj) {
	let allowedTypes = [
		"[object String]",
		"[object Object]",
		"[object Array]",
		"[object Function]"
	];
	
	let objType = Object.prototype.toString.call(obj);
	
	if(obj === null || typeof obj === "undefined") {
		throw new TypeError("Cannot convert undefined or null to object");
	} else if(!~allowedTypes.indexOf(objType)) {
		return [];
	}
	
	// if ES6 is supported
	if (Object.keys) {
		return Object.keys(obj).map(function (key) {
			return [key, obj[key]];
		});
	}
	
	let result = [];
	
	for (let prop in obj)
		if (obj.hasOwnProperty(prop))
			result.push([prop, obj[prop]]);
	
	return objType === "[object Array]"
		? result
		: result.sort(function (a, b) { return a[1] - b[1]; });
};

/**
 * Create an element
 *
 * @param {string=} tag - The elements tag name
 * @param {Object=} attributes - Attributes to be added to the element
 * @param {*=} children - Children of this element
 * @return {HTMLElement}
 */
PayBear.c = function (tag, attributes, children) {
	if (!tag) tag = "div";
	if (!attributes) attributes = {};
	if (!children) children = [];
	
	const elem = document.createElement(tag);
	
	Object.entries(attributes).forEach(function (a) {
		let key = a[0], value = a[1];
		if (!value) return;
		
		if ((typeof value).toLowerCase() === "function") {
			if (key === "ref") value(elem);
			else elem.addEventListener(key, value);
			return;
		}
		
		if (key === "style")
			value = value.replace(/[\t\r\n]/g, " ").trim();
		
		elem.setAttribute(key, value);
	});
	
	if (!Array.isArray(children))
		children = [children];
	
	children.forEach(function (child) {
		if (!child) return;
		
		try {
			elem.appendChild(child);
		} catch (_) {
			elem.appendChild(document.createTextNode(child));
		}
	});
	
	return elem;
};

window.PayBear = PayBear;