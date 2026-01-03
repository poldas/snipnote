# Snipnote Color Scheme Documentation

This document outlines the color palette and design system used in Snipnote, serving as a reference for maintaining visual consistency across the application.

## Primary Brand Colors

### Main Gradient
- **Primary Gradient**: `from-indigo-500 to-purple-600`
- **Secondary Gradient**: `from-indigo-600 to-purple-600`
- **CTA Buttons**: Uses primary gradient with hover effects
- **Logo Background**: Primary gradient

### Background Gradients
- **Main Background**: `from-indigo-50 via-purple-50 to-cyan-50`
- **Glass Effect**: `bg-white/90 backdrop-blur-lg`
- **Overlay Background**: `bg-white/40 backdrop-blur-sm`

## Text Colors

### Primary Text
- **Headings**: `text-indigo-950` (darkest indigo)
- **Subheadings**: `text-indigo-900`
- **Body Text**: `text-slate-700`
- **Secondary Text**: `text-slate-600`
- **Muted Text**: `text-indigo-700/80`

### Accent Text
- **Links**: `text-indigo-600 hover:text-indigo-800`
- **CTA Links**: `text-indigo-700 hover:text-indigo-900`

## Feature Icons & Accents

Each feature card uses a unique gradient to provide visual distinction:

### Feature 1: Speed (Lightning)
- **Icon Background**: `from-yellow-400 to-orange-500`
- **Text**: `text-indigo-950`

### Feature 2: Security (Shield)
- **Icon Background**: `from-green-400 to-emerald-500`
- **Text**: `text-indigo-950`

### Feature 3: Sharing (Network)
- **Icon Background**: `from-indigo-400 to-purple-500`
- **Text**: `text-indigo-950`

### Feature 4: Search (Magnifying Glass)
- **Icon Background**: `from-blue-400 to-cyan-500`
- **Text**: `text-indigo-950`

### Feature 5: History (Clock)
- **Icon Background**: `from-purple-400 to-pink-500`
- **Text**: `text-indigo-950`

### Feature 6: Formatting (Star)
- **Icon Background**: `from-pink-400 to-rose-500`
- **Text**: `text-indigo-950`

## Step Indicators

### Step 1: Create
- **Background**: `from-indigo-500 to-indigo-600`
- **Number Badge**: `bg-indigo-500`

### Step 2: Organize
- **Background**: `from-purple-500 to-purple-600`
- **Number Badge**: `bg-purple-500`

### Step 3: Share
- **Background**: `from-cyan-500 to-blue-600`
- **Number Badge**: `bg-cyan-500`

## CTA Section
- **Background**: `from-indigo-500 via-purple-600 to-cyan-500`
- **Text**: `text-white`
- **Accent Text**: `text-indigo-50`
- **Buttons**: `bg-white` with appropriate text colors

## Form Elements

### Input Fields
- **Border**: `border-slate-300`
- **Focus Border**: `focus:border-indigo-500`
- **Focus Ring**: `focus:ring-2 focus:ring-indigo-200`

### Error States
- **Error Background**: `bg-red-50`
- **Error Border**: `border-red-200`
- **Error Text**: `text-red-700`, `text-red-600`

## Interactive States

### Button Hover Effects
- **Primary Buttons**: `hover:shadow-xl hover:-translate-y-1`
- **Secondary Buttons**: `hover:bg-white` (for glass effect buttons)
- **Links**: `hover:text-indigo-800` or `hover:text-indigo-900`

### White Info Button ("Zobacz możliwości")
- **Normal State**: `bg-white/80 backdrop-blur-sm border-2 border-indigo-200 text-indigo-900`
- **Hover State**: `hover:bg-white` with scale effect `hover:scale-105` and gradient aura
- **Aura Effect**: Gradient blur background `bg-gradient-to-r from-indigo-400 to-purple-400` with `opacity-40` on hover
- **Border Radius**: `rounded-2xl`
- **Typography**: `font-bold text-lg`

### Call to Action (CTA) Buttons
- **Primary CTA**: `btn-auth-primary` class with gradient background and white text
- **Normal State**: `background: linear-gradient(135deg, #667eea 0%, #764ba2 100%)`
- **Hover State**: `background: linear-gradient(135deg, #6366f1 0%, #a855f7 50%, #38bdf8 100%)`
- **Active State**: Solid color `background: #4338ca`
- **Text Color**: Always white across all states
- **Effects**: `hover:shadow-xl hover:-translate-y-1` with smooth transitions

- **Secondary CTA**: Glass effect button for dark backgrounds
- **Normal State**: `bg-white/10 backdrop-blur-sm border-2 border-white/30 text-white`
- **Hover State**: `hover:bg-white/20` with scale effect `hover:scale-105` and white aura
- **Aura Effect**: White blur background `bg-white/20` with `opacity-50` on hover
- **Border Radius**: `rounded-2xl`
- **Typography**: `font-bold text-lg`

### Auth Primary Button Animations
- **CSS Class**: `btn-auth-primary`
- **Normal State**: `background: linear-gradient(135deg, #667eea 0%, #764ba2 100%)` with white text
- **Hover State**: `background: linear-gradient(135deg, #6366f1 0%, #a855f7 50%, #38bdf8 100%)` with white text maintained
- **Active State**: `background: #4338ca` with white text maintained
- **Text Color**: Always white (`color: #fff`) across all states
- **Transition**: `transform 150ms ease, box-shadow 150ms ease, background 160ms ease`

### Card Hover Effects
- **Feature Cards**: `hover:border-indigo-200 hover:shadow-xl`

## Shadows and Effects

### Shadow System
- **Small**: `shadow-lg`
- **Medium**: `shadow-xl`
- **Large**: `shadow-2xl`
- **CTA Buttons**: `shadow-xl hover:shadow-2xl`

### Blur Effects
- **Backdrop Blur**: `backdrop-blur-sm`, `backdrop-blur-lg`
- **Background Blur**: `blur-3xl` for decorative elements

## Color Usage Guidelines

1. **Primary Actions**: Always use the main indigo-to-purple gradient
2. **Secondary Actions**: Use white backgrounds with indigo text and borders
3. **Feature Differentiation**: Use the established feature color gradients
4. **Text Hierarchy**: Follow the established text color scale
5. **Error States**: Use red-50/red-200 backgrounds with red-600/red-700 text
6. **Success States**: Use emerald colors for positive feedback

## Accessibility Considerations

- Ensure sufficient contrast ratios between text and backgrounds
- Use indigo colors for interactive elements to maintain consistency
- Error states should be clearly distinguishable with red accents
- Hover states should provide clear visual feedback

## Implementation Notes

- All colors use Tailwind CSS utility classes
- Gradients are implemented using Tailwind's gradient utilities
- Opacity modifiers are used for glass and backdrop effects
- Color transitions use Tailwind's transition utilities with appropriate durations
- ALWAYS make responsive views