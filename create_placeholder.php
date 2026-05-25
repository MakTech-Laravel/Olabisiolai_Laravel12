<?php

// Create placeholder images for businesses
$placeholderDir = 'public/images/feature';
$colors = [
    '#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#FFEAA7', 
    '#DDA0DD', '#98D8C8', '#F7DC6F', '#85C1E2', '#F8B739'
];

$businesses = [
    ['name' => 'Premium Plumbing Services', 'color' => '#4ECDC4'],
    ['name' => 'Sparkle Clean Services', 'color' => '#45B7D1'],
    ['name' => 'Elite Electrical Solutions', 'color' => '#FF6B6B'],
    ['name' => 'Glamour Beauty Spa', 'color' => '#DDA0DD'],
    ['name' => 'Royal Catering & Events', 'color' => '#F8B739'],
    ['name' => 'Master Builders Ltd', 'color' => '#96CEB4'],
    ['name' => 'Tech Solutions Pro', 'color' => '#85C1E2'],
];

foreach ($businesses as $index => $business) {
    $imageNumber = $index + 1;
    $filename = "placeholder-{$imageNumber}.jpg";
    $filepath = "{$placeholderDir}/{$filename}";
    
    // Create a simple placeholder image using GD
    $width = 400;
    $height = 300;
    
    $image = imagecreatetruecolor($width, $height);
    
    // Convert hex to RGB
    $hex = str_replace('#', '', $business['color']);
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    
    $bgColor = imagecolorallocate($image, $r, $g, $b);
    imagefill($image, 0, 0, $bgColor);
    
    // Add white text
    $textColor = imagecolorallocate($image, 255, 255, 255);
    $fontSize = 20;
    $text = $business['name'];
    
    // Center the text
    $textBox = imagettfbbox($fontSize, 0, 'arial.ttf', $text);
    $textWidth = $textBox[2] - $textBox[0];
    $textHeight = $textBox[1] - $textBox[7];
    $x = ($width - $textWidth) / 2;
    $y = ($height + $textHeight) / 2;
    
    // Use built-in font if TTF not available
    imagestring($image, 5, $x - 50, $y - 10, substr($text, 0, 25), $textColor);
    
    imagejpeg($image, $filepath, 90);
    imagedestroy($image);
    
    echo "Created placeholder: {$filename}\n";
}

echo "All placeholder images created!\n";
