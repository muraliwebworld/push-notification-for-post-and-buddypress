/**
 * PNFPB Accordion Settings
 * 
 * Handles expand/collapse functionality for notification settings cards
 * Provides smooth animations and state persistence via localStorage
 * 
 * @since 1.0.0
 */

(function() {
	'use strict';

	/**
	 * Initialize accordion on document ready
	 */
	function initializeAccordion() {
		const accordionItems = document.querySelectorAll('.pnfpb-section-card');
		
		accordionItems.forEach(function(card, index) {
			const header = card.querySelector('.pnfpb-section-header');
			const body = card.querySelector('.pnfpb-section-body');
			
			if (!header || !body) return;

			// Assign unique ID if not already present
			if (!card.id) {
				card.id = 'pnfpb-section-' + index;
			}

			// Add toggle icon if not present
			if (!header.querySelector('.pnfpb-accordion-toggle')) {
				const toggleIcon = document.createElement('span');
				toggleIcon.classList.add('pnfpb-accordion-toggle');
				toggleIcon.setAttribute('aria-hidden', 'true');
				toggleIcon.innerHTML = '<span class="dashicons dashicons-arrow-down"></span>';
				header.insertAdjacentElement('afterbegin', toggleIcon);
			}

			// Make header interactive
			header.style.cursor = 'pointer';
			header.setAttribute('role', 'button');
			header.setAttribute('tabindex', '0');
			header.setAttribute('aria-expanded', 'true');

			// Restore state from localStorage
			const savedState = localStorage.getItem('pnfpb-accordion-' + card.id);
			if (savedState === 'closed') {
				closeSection(card, body, header);
			} else {
				openSection(card, body, header);
			}

			// Add click event listener
			header.addEventListener('click', function() {
				toggleSection(card, body, header);
			});

			// Add keyboard support (Enter and Space keys)
			header.addEventListener('keydown', function(e) {
				if (e.key === 'Enter' || e.key === ' ') {
					e.preventDefault();
					toggleSection(card, body, header);
				}
			});
		});
	}

	/**
	 * Toggle section open/closed state
	 */
	function toggleSection(card, body, header) {
		const isExpanded = header.getAttribute('aria-expanded') === 'true';
		
		if (isExpanded) {
			closeSection(card, body, header);
		} else {
			openSection(card, body, header);
		}
	}

	/**
	 * Open accordion section
	 */
	function openSection(card, body, header) {
		card.classList.add('pnfpb-section-card--open');
		card.classList.remove('pnfpb-section-card--closed');
		
		body.style.maxHeight = body.scrollHeight + 'px';
		body.style.overflow = 'visible';
		body.style.opacity = '1';
		
		header.setAttribute('aria-expanded', 'true');
		
		// Update toggle icon
		const toggleIcon = header.querySelector('.pnfpb-accordion-toggle');
		if (toggleIcon) {
			toggleIcon.innerHTML = '<span class="dashicons dashicons-arrow-down"></span>';
		}
		
		// Save state
		localStorage.setItem('pnfpb-accordion-' + card.id, 'open');
	}

	/**
	 * Close accordion section
	 */
	function closeSection(card, body, header) {
		card.classList.add('pnfpb-section-card--closed');
		card.classList.remove('pnfpb-section-card--open');
		
		body.style.maxHeight = '0';
		body.style.overflow = 'hidden';
		body.style.opacity = '0';
		
		header.setAttribute('aria-expanded', 'false');
		
		// Update toggle icon
		const toggleIcon = header.querySelector('.pnfpb-accordion-toggle');
		if (toggleIcon) {
			toggleIcon.innerHTML = '<span class="dashicons dashicons-arrow-right"></span>';
		}
		
		// Save state
		localStorage.setItem('pnfpb-accordion-' + card.id, 'closed');
	}

	/**
	 * Expand all accordion sections
	 * Can be called externally if needed
	 */
	window.pnfpbExpandAll = function() {
		const accordionItems = document.querySelectorAll('.pnfpb-section-card');
		accordionItems.forEach(function(card) {
			const header = card.querySelector('.pnfpb-section-header');
			const body = card.querySelector('.pnfpb-section-body');
			if (header && body) {
				openSection(card, body, header);
			}
		});
	};

	/**
	 * Collapse all accordion sections
	 * Can be called externally if needed
	 */
	window.pnfpbCollapseAll = function() {
		const accordionItems = document.querySelectorAll('.pnfpb-section-card');
		accordionItems.forEach(function(card) {
			const header = card.querySelector('.pnfpb-section-header');
			const body = card.querySelector('.pnfpb-section-body');
			if (header && body) {
				closeSection(card, body, header);
			}
		});
	};

	/**
	 * Initialize when DOM is ready
	 */
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initializeAccordion);
	} else {
		initializeAccordion();
	}

	// Re-initialize on window resize to recalculate max-height
	window.addEventListener('resize', function() {
		const openItems = document.querySelectorAll('.pnfpb-section-card--open');
		openItems.forEach(function(card) {
			const body = card.querySelector('.pnfpb-section-body');
			if (body) {
				body.style.maxHeight = body.scrollHeight + 'px';
			}
		});
	});

})();
