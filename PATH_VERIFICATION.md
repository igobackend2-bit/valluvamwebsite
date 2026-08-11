# Path Verification for Live Server

## ✅ All Paths Verified and Fixed

### PHP Backend Paths
All PHP files now use `__DIR__` for cross-platform compatibility:
- ✅ All `require_once` statements use `__DIR__ . '/../config.php'` pattern
- ✅ All vendor paths use `__DIR__ . '/../../../vendor/...'` pattern
- ✅ All upload paths use `__DIR__ . '/../../uploads/'` pattern

### Fixed Files:
1. ✅ `save.php` - Fixed hardcoded Windows path
2. ✅ `assets/db_query/contact/contact_query.php` - Fixed contact folder path

### JavaScript/AJAX Paths
All JavaScript paths are relative and should work on live server:
- ✅ `checkout.php` - All AJAX URLs use relative paths: `assets/db_query/...`
- ✅ `admin/*.php` - All AJAX URLs use relative paths: `../assets/db_query/...`

### Important Notes for Live Server:

1. **File Permissions:**
   - `assets/uploads/` folder should have write permissions (755 or 775)
   - `assets/contact/` folder should have write permissions (755 or 775)

2. **Database Configuration:**
   - Update `assets/db_query/config.php` with live server database credentials
   - Ensure database name, username, and password are correct

3. **Razorpay Keys:**
   - Update `assets/db_query/razorpay.php` with live Razorpay keys (not test keys)

4. **Vendor Directory:**
   - Ensure `vendor/` directory is uploaded to server
   - Run `composer install` on server if needed

5. **Session Storage:**
   - Ensure PHP sessions are working (check `php.ini` session settings)
   - Session files directory should be writable

6. **Error Logging:**
   - Uncomment error logging in `assets/db_query/order/create.php` if needed
   - Set appropriate error log path for your server

### Testing Checklist:
- [ ] All file paths work correctly
- [ ] Image uploads work
- [ ] Database connections work
- [ ] Razorpay payments work
- [ ] Email sending works
- [ ] File permissions are correct
- [ ] Sessions work correctly

