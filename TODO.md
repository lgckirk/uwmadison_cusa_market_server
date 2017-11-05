1. Write a script to check if the image count in the database matches the actual count in the S3 bucket
2. Check image size as well as other input.
3. Perfect the exception flow.
4. Update the interfaces and related functions to have it return detailed error/exception messages instead of just returning true or false for success/failure. An example is FetchImage() function, which could fail for many reasons but does not inform the caller.
5. For image URL, should use S3 pre-signed URL instead of accessing bucket directly.
6. Put files into directory instead of having them all in the root directory.