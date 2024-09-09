// Wait for the DOM to be fully loaded before running the script
document.addEventListener('DOMContentLoaded', function() {
    // Email form submission in the sign-up section
    const emailButton = document.querySelector('#sign-up-cta .button');
    if (emailButton) {
        emailButton.addEventListener('click', function(e) {
            e.preventDefault();
            const email = prompt("Please enter your email address:");
            if (email) {
                alert(`Thank you! We've added ${email} to our waiting list for the Doorstep Tryout facility.`);
                // Here you would typically send this to a server
            }
        });
    }

    // Image gallery modal for the filters section
    const filterImages = document.querySelectorAll('#filters-section .image-container img');
    filterImages.forEach(img => {
        img.addEventListener('click', function() {
            const modal = document.createElement('div');
            modal.style.position = 'fixed';
            modal.style.top = '0';
            modal.style.left = '0';
            modal.style.width = '100%';
            modal.style.height = '100%';
            modal.style.backgroundColor = 'rgba(0,0,0,0.8)';
            modal.style.display = 'flex';
            modal.style.justifyContent = 'center';
            modal.style.alignItems = 'center';
            modal.style.zIndex = '1000';

            const modalImg = document.createElement('img');
            modalImg.src = this.src;
            modalImg.style.maxWidth = '90%';
            modalImg.style.maxHeight = '90%';
            modalImg.style.objectFit = 'contain';

            modal.appendChild(modalImg);
            document.body.appendChild(modal);

            modal.addEventListener('click', function() {
                document.body.removeChild(modal);
            });
        });
    });


    // NEW: Interactive feature cards
    const features = document.querySelectorAll('.feature');
    features.forEach(feature => {
        feature.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.05)';
            this.style.transition = 'transform 0.3s ease';
        });
        feature.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
        });
    });

    // NEW: Dynamic quote carousel
    const quotesSection = document.querySelector('#quotes-section .content');
    if (quotesSection) {
        const quotes = [
            "Each piece is a masterpiece of craftsmanship and design, showcasing an unparalleled attention to detail.",
            "Prism Jewellery redefines elegance with its timeless collections.",
            "Discover the perfect blend of tradition and modernity in every Prism creation."
        ];
        let currentQuote = 0;

        function changeQuote() {
            quotesSection.style.opacity = 0;
            setTimeout(() => {
                quotesSection.querySelector('.quote').textContent = quotes[currentQuote];
                quotesSection.style.opacity = 1;
                currentQuote = (currentQuote + 1) % quotes.length;
            }, 300);
        }

        quotesSection.addEventListener('click', changeQuote);

        // Add a visual cue to indicate the quote is clickable
        quotesSection.style.cursor = 'pointer';
        const clickInstruction = document.createElement('p');
        clickInstruction.textContent = 'Click to see another quote';
        clickInstruction.style.fontSize = '0.8em';
        clickInstruction.style.marginTop = '10px';
        clickInstruction.style.fontStyle = 'italic';
        quotesSection.appendChild(clickInstruction);
    }
});