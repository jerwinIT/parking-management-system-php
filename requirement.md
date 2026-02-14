ParkIt: Parking Management System
Session 1
Problem Statement
What problem does this system solve?
The ParkIt: Parking Management System solves the problem of inefficient, unorganized, and manual parking operations. The system addresses the inefficiencies of manual parking management by automating parking slot monitoring, vehicle tracking, and fee computation.
User Persona


Persona 1: Admin 
Name:
John Doe
Age: 
35
Role:
System Administrator
Tech Skill Level:
Medium - High
Background:
Alex is responsible for managing the overall parking operations. He oversees parking slots, monitors vehicle activity, and ensures that parking records are accurate. He previously relied on manual logs, which were time-consuming and prone to errors.
Goals:
Monitor parking slot availability in real time


Ensure accurate recording of vehicle entry and exit


Generate parking reports easily


Reduce errors and improve operational efficiency


Responsibilities:
Monitor real-time parking slot availability


Add, update, or remove parking slots


Record and verify vehicle entry and exit data


Ensure accurate parking time and fee computation


View and generate parking reports


Maintain the accuracy and integrity of parking records


Manage system settings and basic configurations


Assist users in resolving parking-related issues
Pain Points:
Manual record-keeping is slow and inefficient


Difficulty tracking parking history


Inaccurate or inconsistent parking data


Time-consuming report preparation


Needs:
Centralized dashboard to monitor parking status


Automated logging of vehicles


Easy access to parking reports


Simple and reliable system interface


Persona 2: User
Name:
Jane Doe
Age: 
22
Role:
Driver/ Parking User
Tech Skill Level:
Basic
Background:
Jamie regularly uses parking facilities for school or work. Finding an available parking slot often takes time, especially during peak hours, causing stress and delays.
Goals:
Find available parking slots quickly


Experience a smooth parking process


Avoid long waiting times


Ensure fair parking fees


Responsibilities:
Monitor real-time parking slot availability


Add, update, or remove parking slots


Record and verify vehicle entry and exit data


Ensure accurate parking time and fee computation


View and generate parking reports


Maintain the accuracy and integrity of parking records


Manage system settings and basic configurations


Assist users in resolving parking-related issues
Pain Points:
No visibility of available parking spaces


Time wasted searching for parking


Long queues during entry and exit


Unclear or inconsistent parking fees


Needs:
Clear information on available parking slots


Fast entry and exit process


Accurate time tracking


Transparent fee computation

User Stories
Admin
Manage parking slots
As an Admin, I want to add or remove parking slots, so that the parking facility stays up-to-date and organized.
Monitor vehicle activity
As an Admin, I want to view real-time vehicle entry and exit logs, so that I can track parking usage efficiently.
Generate reports
As an Admin, I want to generate daily and monthly parking reports, so that I can analyze usage trends and improve operations.
User
Find available parking slots
As a User, I want to see which parking slots are available, so that I can park quickly without wasting time.
Record vehicle entry and exit
As a User, I want the system to log my entry and exit automatically, so that I don’t have to manually track my parking time.
View and pay parking fees
As a User, I want to see the parking fees based on my parking duration, so that I can pay the correct amount easily.
Acceptance Criteria
Admin User Story:
As an Admin, I want to monitor real-time parking slot availability and vehicle activity, so that I can ensure smooth operations and quickly respond to issues.
Admin can view a dashboard that shows all parking slots with current status (Available/ Occupied).
Admin can see a list of all vehicles currently parked, including:
Plate number
Assigned parking slot
Time-in
The dashboard updates in real-time when a vehicle enters or exits.
Admin can filter or search by parking slot or vehicle plate number.
Any errors are flagged to the admin.
Dashboard loads within 5 seconds and displays accurate data every time.

User Story:
As a User, I want to see which parking slots are available, so that I can park quickly without wasting time.
System displays a visual list or map of all parking slots.
Each slot clearly shows available or occupied status.
Status updates in real-time when a vehicle parks or leaves.
User can select an available slot and the system reserves it immediately.
No occupied slots are shown as available, and no errors occur during selection.

Session 2
High-Level Architecture
Frontend (HTML, CSS, JavaScript)
        ↓
Backend API (PHP)
        ↓
Database (MySQL)
        ↓
External Services (Mock Payment Gateway)

Users interact via a web interface (HTML/CSS).
The frontend communicates with the Backend API for data operations.
Data is stored in MySQL.
Payment is simulated via a mock payment service.
 Frontend
Technologies: HTML, CSS, JavaScript
Responsibilities:
Display parking slots
Input vehicle data
Show fees and reports
Handle user login
Page:
Login page
Dashboard (Admin)
Parking slot view (User)
Vehicle entry/exit
Reports
Payments

Backend API
Technology: PHP
Responsibilities:
Process requests from frontend
Perform CRUD operations on MySQL database
Calculate parking fees
Handle authentication and roles
Simulate payment transactions
Database
Technology: MySQL
Responsibilities:
Store users, roles, parking data, payments, orders
Maintain data integrity
Support reporting
 External Services
Mock Payment Gateway
Simulate online payment for parking fees
Accept payment data from API
Return transaction success/failure
Database Design (ER Diagram)

Roles table


Column Name
Type
role_id
INT
role_name
VARCHAR(50)
description
VARCHAR(255)

Users table
Column Name
Type
user_id
INT
role_id
INT
username
VARCHAR(50)
password
VARCHAR(255)
full_name
VARCHAR(100)
email
VARCHAR(100)
phone
VARCHAR(20)
created_at
DATETIME

Parking_slots table

Column Name
Type
slot_id
INT
slot_number
VARCHAR(10)
floor
INT
type
VARCHAR(20)
status
VARCHAR(20)

Vehicles table
Column Name
Type
vehicle_id
INT
user_id
INT
plate_number
VARCHAR(20)
type
VARCHAR(20)
brand
VARCHAR(50)
color
VARCHAR(20)

Bookings table
Column Name
Type
booking_id
INT
user_id
INT
vehicle_id
INT
slot_id
INT
start_time
DATETIME
end_time
DATETIME
status
VARCHAR(20)

Payments table
Column Name
Type
payment_id
INT
booking_id
INT
amount
DECIMAL(10,2)
payment_date
DATETIME
payment_method
VARCHAR(50)
status
VARCHAR(20)




Dashboard - widgets (available slot, taken, total) 
book a slot - login - user information(owner name, contact number), vehicle(type, plate number), payment - book

Sidebar ( Login (user- register car info, history w/ status; admin - provide total number of slots, open/ closing time) (Parking map - shows the car inside the parking lot)





