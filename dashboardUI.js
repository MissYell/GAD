// dashboardUI.js - Dynamic Donut Chart with Database Data

document.addEventListener('DOMContentLoaded', function() {
    // Get canvas element
    const canvas = document.getElementById('donutChart');
    if (!canvas) {
        console.error('Canvas element not found');
        return;
    }

    const ctx = canvas.getContext('2d');
    
    // Set canvas size for high DPI displays
    const rect = canvas.getBoundingClientRect();
    const dpr = window.devicePixelRatio || 1;
    canvas.width = rect.width * dpr;
    canvas.height = rect.height * dpr;
    ctx.scale(dpr, dpr);
    
    const centerX = rect.width / 2;
    const centerY = rect.height / 2;
    const outerRadius = Math.min(centerX, centerY) - 20;
    const innerRadius = outerRadius * 0.6;
    
    // Chart colors
    const colors = {
        pending: '#fbbf24',      
        for_correction: '#f87171', 
        completed: '#34d399'     
    };
    
    // FIXED: Get data from PHP (now properly accessible via window.dashboardData)
    const data = window.dashboardData || {
        pending: 0,
        for_correction: 0,
        completed: 0,
        total: 0
    };
    
    console.log('Dashboard Data:', data); // Debug log
    console.log('Data total:', data.total);
    console.log('Pending:', data.pending);
    console.log('For correction:', data.for_correction); 
    console.log('Completed:', data.completed);
    
    // Calculate angles for each segment
    function calculateAngles() {
        const total = data.total;
        if (total === 0) {
            return [
                { label: 'no_data', value: 0, percentage: 0, startAngle: 0, endAngle: 2 * Math.PI, color: '#e5e7eb' }
            ];
        }
        
        const segments = [];
        let currentAngle = -Math.PI / 2; // Start from top
        
        const segmentData = [
            { key: 'pending', value: data.pending, color: colors.pending },
            { key: 'for_correction', value: data.for_correction, color: colors.for_correction },
            { key: 'completed', value: data.completed, color: colors.completed }
        ];
        
        segmentData.forEach(segment => {
            if (segment.value > 0) {
                const percentage = (segment.value / total) * 100;
                const segmentAngle = (segment.value / total) * 2 * Math.PI;
                
                segments.push({
                    label: segment.key,
                    value: segment.value,
                    percentage: percentage,
                    startAngle: currentAngle,
                    endAngle: currentAngle + segmentAngle,
                    color: segment.color
                });
                
                currentAngle += segmentAngle;
            }
        });
        
        return segments;
    }
    
    // Draw donut chart
    function drawChart() {
        // Clear canvas
        ctx.clearRect(0, 0, rect.width, rect.height);
        
        const segments = calculateAngles();
        
        // Draw segments
        segments.forEach((segment, index) => {
            // Draw segment
            ctx.beginPath();
            ctx.arc(centerX, centerY, outerRadius, segment.startAngle, segment.endAngle);
            ctx.arc(centerX, centerY, innerRadius, segment.endAngle, segment.startAngle, true);
            ctx.closePath();
            ctx.fillStyle = segment.color;
            ctx.fill();
            
            // Add subtle shadow
            ctx.shadowColor = 'rgba(0, 0, 0, 0.1)';
            ctx.shadowBlur = 4;
            ctx.shadowOffsetX = 2;
            ctx.shadowOffsetY = 2;
            ctx.fill();
            
            // Reset shadow
            ctx.shadowColor = 'transparent';
            ctx.shadowBlur = 0;
            ctx.shadowOffsetX = 0;
            ctx.shadowOffsetY = 0;
        });
        
        // Draw subtle border between segments
        if (segments.length > 1) {
            segments.forEach(segment => {
                ctx.beginPath();
                ctx.arc(centerX, centerY, outerRadius, segment.startAngle, segment.endAngle);
                ctx.arc(centerX, centerY, innerRadius, segment.endAngle, segment.startAngle, true);
                ctx.closePath();
                ctx.strokeStyle = '#ffffff';
                ctx.lineWidth = 2;
                ctx.stroke();
            });
        }
        
        // If no data, draw empty state
        if (data.total === 0) {
            ctx.beginPath();
            ctx.arc(centerX, centerY, outerRadius, 0, 2 * Math.PI);
            ctx.arc(centerX, centerY, innerRadius, 2 * Math.PI, 0, true);
            ctx.closePath();
            ctx.fillStyle = '#e5e7eb';
            ctx.fill();
            
            // Draw border
            ctx.strokeStyle = '#d1d5db';
            ctx.lineWidth = 1;
            ctx.stroke();
        }
    }
    
    // Animation function
    function animateChart() {
        let progress = 0;
        const duration = 1000; // 1 second
        const startTime = Date.now();
        
        function animate() {
            const elapsed = Date.now() - startTime;
            progress = Math.min(elapsed / duration, 1);
            
            // Easing function (ease-out)
            const easedProgress = 1 - Math.pow(1 - progress, 3);
            
            // Clear canvas
            ctx.clearRect(0, 0, rect.width, rect.height);
            
            const segments = calculateAngles();
            
            // Draw animated segments
            segments.forEach(segment => {
                const animatedEndAngle = segment.startAngle + (segment.endAngle - segment.startAngle) * easedProgress;
                
                ctx.beginPath();
                ctx.arc(centerX, centerY, outerRadius, segment.startAngle, animatedEndAngle);
                ctx.arc(centerX, centerY, innerRadius, animatedEndAngle, segment.startAngle, true);
                ctx.closePath();
                ctx.fillStyle = segment.color;
                ctx.fill();
            });
            
            if (progress < 1) {
                requestAnimationFrame(animate);
            } else {
                // Final draw with borders
                drawChart();
            }
        }
        
        animate();
    }
    
    // Add hover effects
    canvas.addEventListener('mousemove', function(e) {
        const rect = canvas.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        
        const dx = x - centerX;
        const dy = y - centerY;
        const distance = Math.sqrt(dx * dx + dy * dy);
        
        // Check if mouse is over the donut
        if (distance >= innerRadius && distance <= outerRadius) {
            canvas.style.cursor = 'pointer';
            
            // Calculate angle
            let angle = Math.atan2(dy, dx) + Math.PI / 2;
            if (angle < 0) angle += 2 * Math.PI;
            
            const segments = calculateAngles();
            const hoveredSegment = segments.find(segment => 
                angle >= segment.startAngle && angle <= segment.endAngle
            );
            
            if (hoveredSegment && hoveredSegment.label !== 'no_data') {
                // Show tooltip (you can customize this)
                canvas.title = `${hoveredSegment.label.replace('_', ' ').toUpperCase()}: ${hoveredSegment.value} (${hoveredSegment.percentage.toFixed(1)}%)`;
            }
        } else {
            canvas.style.cursor = 'default';
            canvas.title = '';
        }
    });
    
    canvas.addEventListener('mouseleave', function() {
        canvas.style.cursor = 'default';
        canvas.title = '';
    });
    
    // Initial chart render with animation
    animateChart();
    
    // Redraw on window resize
    window.addEventListener('resize', function() {
        setTimeout(drawChart, 100);
    });
});

// FIXED: Utility function to refresh dashboard data
function refreshDashboardData() {
    fetch('get_dashboard_data.php', {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        // Update global data
        window.dashboardData = data;
        
        // Update the displayed numbers with proper selectors
        const pendingElement = document.querySelector('[data-status="pending"] .count');
        const correctionElement = document.querySelector('[data-status="for_correction"] .count');
        const completedElement = document.querySelector('[data-status="completed"] .count');
        const totalElement = document.querySelector('.total-count');
        
        if (pendingElement) pendingElement.textContent = data.pending;
        if (correctionElement) correctionElement.textContent = data.for_correction;
        if (completedElement) completedElement.textContent = data.completed;
        if (totalElement) totalElement.textContent = data.total;
        
        // Update percentages
        const pendingPercentage = document.querySelector('[data-status="pending"] .percentage');
        const correctionPercentage = document.querySelector('[data-status="for_correction"] .percentage');
        const completedPercentage = document.querySelector('[data-status="completed"] .percentage');
        
        if (pendingPercentage && data.percentages) pendingPercentage.textContent = data.percentages.pending + '%';
        if (correctionPercentage && data.percentages) correctionPercentage.textContent = data.percentages.for_correction + '%';
        if (completedPercentage && data.percentages) completedPercentage.textContent = data.percentages.completed + '%';
        
        // Redraw chart
        const canvas = document.getElementById('donutChart');
        if (canvas) {
            const ctx = canvas.getContext('2d');
            const rect = canvas.getBoundingClientRect();
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            
            // Re-run the animation
            setTimeout(() => {
                const event = new Event('DOMContentLoaded');
                document.dispatchEvent(event);
            }, 100);
        }
    })
    .catch(error => {
        console.error('Error refreshing dashboard data:', error);
    });
}