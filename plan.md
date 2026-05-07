Interactive Vehicle Glass Form — Start Plan
Main concept

We are building a vehicle glass selection form for clients who need window repair or replacement.

The system will first ask the client for vehicle information:

Year
Brand
Model
Body style
VIN optional

After the vehicle information is selected, the user goes to the next screen where they see a simple car SVG model. The user can rotate/switch the car view and click the damaged window.

Important: We do not need a different SVG for every brand like BMW, Mercedes, Audi, etc.
We only need SVG models by body category.

Example:

BMW sedan → use sedan SVG
Mercedes sedan → use sedan SVG
Audi sedan → use sedan SVG
VW combi → use combi SVG
Ford pickup → use pickup SVG

The brand/model helps identify the vehicle, but the visual selector uses the body style.

First version: only 3 steps
Step 1: Vehicle information

Create a form screen like the example design.

Fields:

Year
Brand
Model
Body Style
VIN optional

The user selects the car details.

The system stores:

selected_year
selected_brand
selected_model
selected_body_style
vin_optional

After clicking Continue, the system opens the glass selector page.

Step 2: Vehicle glass selector

Show a clean car SVG without extra text inside the car area.

The car should appear as a large visual selector.

The user can rotate/switch views using buttons:

Right side
Left side
Front
Back
Top / sunroof view

This is not real 3D rotation. It is SVG view switching.

Each body category needs its own folder with SVG views.

Example folder structure:

vehicles/
  sedan/
    right.svg
    left.svg
    front.svg
    back.svg
    top.svg

  combi/
    right.svg
    left.svg
    front.svg
    back.svg
    top.svg

  pickup/
    right.svg
    left.svg
    front.svg
    back.svg
    top.svg

For the first MVP, create only:

vehicles/
  sedan/
    right.svg
    left.svg
    front.svg
    back.svg
    top.svg

Later we will add combi, pickup, SUV, van, etc.

Step 3: Select window and open options

When the user clicks a window on the SVG:

The selected window becomes highlighted
The selected window name is stored
A panel opens with more options for that window
The car stays visible and does not disappear
The user can remove the selected window with a small X

The right/side panel should show:

Selected window name
Type of damage
Special features
Repair or replacement choice
Photo upload
Notes field
Continue button

Example damage options:

Chip / Crack
Shattered / Broken
Scratched
Leaking
Not sure

Example special glass features:

Heated glass
ADAS / cameras
Acoustic glass
Factory tint
Rain sensor
Not sure

Sedan window structure

For sedan, use this basic window list:

front_windshield
rear_window
left_front_door_window
left_rear_door_window
left_quarter_window
right_front_door_window
right_rear_door_window
right_quarter_window
sunroof_glass

That gives us 9 main glass parts.

Side view shows:

front door window
rear door window
quarter window

Front view shows:

front windshield

Back view shows:

rear window

Top view shows:

sunroof glass

SVG rule

Every clickable window inside the SVG must have a clean ID.

Do not use random IDs like:

path123
shape45
group88

Use exact names:

front_windshield
rear_window
left_front_door_window
left_rear_door_window
left_quarter_window
right_front_door_window
right_rear_door_window
right_quarter_window
sunroof_glass

The same names must be used in:

SVG
JavaScript
PHP backend
database
admin dashboard

Technical setup

Frontend:

HTML
CSS
JavaScript

Backend:

PHP

SVG files:

Created in Figma
Exported as SVG
Stored by body category folders

The PHP backend should save the final submitted data, but the car interaction should happen with JavaScript.

Important implementation rule

Do not overcomplicate the first version.

Do not build:

real 3D car
all car brands as separate SVGs
automatic price calculation
advanced animations
AI damage detection

Start only with:

vehicle form
sedan SVG selector
view buttons
clickable windows
window options panel
remove selected window with X
submit selected data to PHP