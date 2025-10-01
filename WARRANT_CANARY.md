# Warrant Canary

## What is a Warrant Canary?

A warrant canary is a method by which a service provider can inform users that they have **NOT** been served with secret government subpoenas, warrants, or gag orders. The concept works on the principle that while organizations may be legally prohibited from disclosing that they have received such orders, they are generally not prohibited from stating that they have NOT received them.

## How It Works

1. **Regular Updates**: The canary page is updated on a regular schedule (quarterly in our case)
2. **Positive Statements**: The page contains statements affirming that no secret legal processes have been received
3. **Passive Warning**: If the page is not updated, removed, or if statements are modified/removed, users may infer that legal processes have been served

## Our Implementation

The warrant canary is accessible at: `/canary`

### Features

- **Quarterly Updates**: The canary is scheduled to be updated every 3 months
- **Clear Statements**: Lists specific types of legal processes that have NOT been received
- **Date Tracking**: Shows last update date and next scheduled update
- **Visual Design**: Terminal-style design appropriate for a Tor hidden service
- **Transparency**: Explains what a warrant canary is and how to interpret it

### Statements Included

As of the last update, we declare that:

- No National Security Letters have been received
- No gag orders have been served
- No warrants from any government entity have been served
- No subpoenas from any government entity have been served
- No court orders compelling disclosure of user data have been received
- No requests to install surveillance software have been received
- The integrity of our systems has not been compromised
- We have not been forced to modify our systems to facilitate surveillance

## Maintenance

### Updating the Canary

The canary should be updated **at least quarterly**. To update:

1. Access the canary controller at `src/Controllers/CanaryController.php`
2. Verify all statements remain true
3. The dates will automatically update based on the current date
4. If any statement is no longer true, **do not update the canary** - this serves as the warning

### Important Notes

- **Never lie**: If you cannot truthfully make all statements, do not update the canary
- **Regular schedule**: Maintain a consistent update schedule so users know when to expect updates
- **Multiple channels**: Consider announcing updates through multiple channels for verification
- **Legal review**: Consult with legal counsel about the specific wording and implications in your jurisdiction

## Legal Considerations

⚠️ **Important**: Warrant canaries exist in a legal gray area. Their effectiveness and legality may vary by jurisdiction. This implementation:

- Does not constitute legal advice
- May not be effective in all jurisdictions
- Should be reviewed by legal counsel before deployment
- May not protect against all types of legal compulsion

## For Users

If you notice any of the following, it may indicate that legal processes have been served:

1. The canary page is removed or inaccessible
2. The canary is not updated by the scheduled date
3. Any statements are removed or modified
4. The page content changes significantly without explanation
5. The update schedule becomes irregular

## Technical Implementation

### Files Created

- `src/Controllers/CanaryController.php` - Controller handling the canary page
- `templates/canary/index.php` - Template displaying the canary
- Route added to `src/Core/Application.php` at `/canary`

### Customization

You can customize:

- Update frequency (currently quarterly)
- Statements included
- Visual design
- Additional information or context

## References

- [Wikipedia: Warrant Canary](https://en.wikipedia.org/wiki/Warrant_canary)
- [EFF: Warrant Canary FAQ](https://www.eff.org/deeplinks/2014/04/warrant-canary-faq)

## Changelog

- **2025-10-01**: Initial implementation with quarterly update schedule