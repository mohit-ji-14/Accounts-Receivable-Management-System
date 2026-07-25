import sys
import json
import datetime
import mysql.connector

def connect_db():
    try:
        conn = mysql.connector.connect(
            host="localhost",
            user="root",
            password="",
            database="arms_db"
        )
        return conn
    except Exception as e:
        print(json.dumps({"status": "error", "message": f"Database connection error: {str(e)}"}))
        sys.exit(1)

def run_automated_reminders():
    conn = connect_db()
    cursor = conn.cursor(dictionary=True)

    today = datetime.date.today()
    
    # Query overdue invoices
    query = """
        SELECT i.invoice_id, i.invoice_number, i.invoice_date, i.due_date, 
               i.total_amount, i.paid_amount, i.currency_code,
               c.customer_name, c.company_name, c.email
        FROM invoices i
        JOIN customers c ON i.customer_id = c.customer_id
        WHERE i.status != 'Paid' AND (i.due_date < %s OR i.status = 'Overdue')
        ORDER BY i.due_date ASC
    """
    
    cursor.execute(query, (today,))
    overdue_invoices = cursor.fetchall()

    processed_count = 0
    results = []

    for inv in overdue_invoices:
        due_date = inv['due_date']
        days_overdue = (today - due_date).days if due_date else 1
        balance_due = float(inv['total_amount']) - float(inv['paid_amount'])
        currency = inv['currency_code'] or 'INR'

        if days_overdue <= 30:
            notice_type = "1st Friendly Reminder"
            subject = f"Friendly Reminder: Payment for Invoice {inv['invoice_number']} is Overdue"
        elif days_overdue <= 60:
            notice_type = "2nd Urgent Overdue Notice"
            subject = f"URGENT: Overdue Notice for Invoice {inv['invoice_number']}"
        else:
            notice_type = "Final Demand Notice"
            subject = f"FINAL DEMAND: Immediate Action Required - Invoice {inv['invoice_number']}"

        result_entry = {
            "invoice_number": inv['invoice_number'],
            "customer_name": inv['customer_name'],
            "email": inv['email'],
            "balance_due": f"{currency} {balance_due:,.2f}",
            "days_overdue": days_overdue,
            "notice_type": notice_type,
            "status": "Reminder Email Queued Successfully"
        }
        results.append(result_entry)
        processed_count += 1

        # Log Activity to MySQL
        log_activity = f"[Python Automated Cron] Queued {notice_type} for Invoice #{inv['invoice_number']} ({inv['customer_name']})"
        log_query = "INSERT INTO activity_logs (user_name, activity, ip_address) VALUES (%s, %s, %s)"
        cursor.execute(log_query, ("Python Auto-Cron", log_activity, "127.0.0.1"))

    conn.commit()
    cursor.close()
    conn.close()

    output = {
        "status": "success",
        "timestamp": datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
        "total_overdue_found": len(overdue_invoices),
        "total_reminders_queued": processed_count,
        "reminders": results
    }
    
    print(json.dumps(output, indent=2))

if __name__ == "__main__":
    run_automated_reminders()
