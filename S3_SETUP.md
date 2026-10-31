# S3/MinIO Setup

This project uses MinIO (S3-compatible storage) for file storage. In the future, you can switch to AWS S3 by updating the environment variables.

## Environment Variables

Add these variables to your `.env` file:

```env
# Filesystem
FILESYSTEM_DISK=s3

# MinIO (Development)
AWS_ACCESS_KEY_ID=minioadmin
AWS_SECRET_ACCESS_KEY=minioadmin
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=ailitreview
AWS_ENDPOINT=http://minio:9000
AWS_USE_PATH_STYLE_ENDPOINT=true

# For AWS S3 (Production - uncomment and update when ready)
# AWS_ACCESS_KEY_ID=your_aws_access_key
# AWS_SECRET_ACCESS_KEY=your_aws_secret_key
# AWS_DEFAULT_REGION=us-east-1
# AWS_BUCKET=your_bucket_name
# AWS_URL=https://your_bucket_name.s3.amazonaws.com
# AWS_USE_PATH_STYLE_ENDPOINT=false
```

## MinIO Access

- **MinIO Console**: http://localhost:9002
- **MinIO API**: http://localhost:9000
- **Default Credentials**: 
  - Username: `minioadmin`
  - Password: `minioadmin`

## Initializing the Bucket

The bucket is automatically created when you first upload a file. However, if you need to manually create it:

1. Access MinIO Console at http://localhost:9002
2. Login with default credentials
3. Create a bucket named `ailitreview`
4. Set the bucket policy to allow public read access (for avatar images)

Or use the provided script:
```bash
bash init-minio.sh
```

## Switching to AWS S3

When ready to use AWS S3:

1. Update the environment variables in `.env` with your AWS credentials
2. Change `AWS_ENDPOINT` to your S3 endpoint (or remove it)
3. Set `AWS_USE_PATH_STYLE_ENDPOINT=false`
4. Update `AWS_BUCKET` to your S3 bucket name
5. The code will automatically use AWS S3 instead of MinIO
